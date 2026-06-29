# AWS ECS Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy the Laravel multi-tenant SSO boilerplate to AWS ECS Fargate at `multi-tenancy.ronmartdanieljavier.com` with GitHub Actions CI/CD.

**Architecture:** All app containers (nginx, php-fpm, postgres, redis, horizon) run in a single ECS Fargate task so they share `localhost` — mirroring the docker-compose setup. An Application Load Balancer terminates HTTPS with a free ACM cert and forwards to the nginx container on port 80. GitHub Actions builds Docker images on push to `main`, pushes to ECR, and triggers a rolling ECS deploy via OIDC (no long-lived AWS keys stored in GitHub).

**Tech Stack:** Terraform ≥ 1.6 (IaC), AWS ECS Fargate, ECR, EFS, ALB, ACM, Secrets Manager, GitHub Actions, PHP 8.5-fpm-alpine, PostgreSQL 17-alpine, Redis 7-alpine, Nginx Alpine.

## Global Constraints

- AWS region: `us-east-1`
- Domain: `multi-tenancy.ronmartdanieljavier.com` (DNS at A2Hosting)
- GitHub repo: `ronmartdanieljavier/laravel-multitenant-sso-boilerplate`
- Terraform state: S3 bucket + DynamoDB lock table (created manually before first `terraform apply`)
- All app secrets stored in AWS Secrets Manager — never in Terraform state or GitHub secrets
- EFS provides persistent volumes for postgres data, redis data, and Laravel `storage/`
- ECS task: 1 vCPU / 2 GB RAM (all containers share this allocation)
- Horizon runs as a non-essential sidecar container so the task stays up if horizon crashes

---

## File Map

**New files to create:**

| File | Responsibility |
|---|---|
| `docker/nginx/Dockerfile` | Build nginx image with config baked in for ECR |
| `docker/php/start.sh` | Startup script: runs migrations then exec php-fpm |
| `infrastructure/terraform/main.tf` | Terraform provider + S3 backend config |
| `infrastructure/terraform/variables.tf` | All input variables |
| `infrastructure/terraform/outputs.tf` | ALB DNS, ECR URLs, ACM CNAME, GitHub role ARN |
| `infrastructure/terraform/vpc.tf` | VPC, subnets, IGW, route tables, security groups |
| `infrastructure/terraform/ecr.tf` | ECR repos for app and nginx images |
| `infrastructure/terraform/efs.tf` | EFS filesystem, mount targets, access points |
| `infrastructure/terraform/secrets.tf` | Secrets Manager secret (values set via AWS CLI) |
| `infrastructure/terraform/iam.tf` | ECS execution role, task role, GitHub OIDC role |
| `infrastructure/terraform/ecs.tf` | ECS cluster, task definition (all 5 containers), service |
| `infrastructure/terraform/alb.tf` | ALB, target group, HTTP redirect, HTTPS listener, ACM cert |
| `.github/workflows/ci.yml` | Test job (all pushes + PRs), deploy job (main only) |

**Files to modify:**

| File | Change |
|---|---|
| `docker/nginx/default.conf` | `fastcgi_pass php:9000` → `fastcgi_pass 127.0.0.1:9000` |
| `docker/php/Dockerfile` | Change `CMD` to run `start.sh` |
| `routes/web.php` | Add `/health` route returning 200 |

---

## Task 1: Prerequisites — Install Tools

**Files:** none (local machine setup)

- [ ] **Step 1: Install Terraform**

```bash
brew tap hashicorp/tap
brew install hashicorp/tap/terraform
terraform -version
# Expected: Terraform v1.6.x or higher
```

- [ ] **Step 2: Install AWS CLI v2**

```bash
brew install awscli
aws --version
# Expected: aws-cli/2.x.x
```

- [ ] **Step 3: Create an IAM user for initial bootstrap**

In the [AWS Console](https://console.aws.amazon.com/iam/):
1. Go to IAM → Users → Create user
2. Name: `terraform-bootstrap`
3. Attach policy: `AdministratorAccess` (you will delete this user after Terraform sets up proper OIDC)
4. Create access key → type: CLI
5. Save the Access Key ID and Secret Access Key

- [ ] **Step 4: Configure AWS CLI**

```bash
aws configure
# AWS Access Key ID: <your key>
# AWS Secret Access Key: <your secret>
# Default region name: us-east-1
# Default output format: json

# Verify
aws sts get-caller-identity
# Expected: JSON with your account ID
```

---

## Task 2: Docker Prep — Update Nginx Config and Startup Script

**Files:**
- Modify: `docker/nginx/default.conf`
- Create: `docker/nginx/Dockerfile`
- Create: `docker/php/start.sh`
- Modify: `docker/php/Dockerfile`
- Modify: `routes/web.php`

In ECS, all containers in a task share `localhost` — the docker-compose service name `php` doesn't exist. The nginx config must use `127.0.0.1:9000` instead.

- [ ] **Step 1: Update nginx fastcgi_pass**

In `docker/nginx/default.conf`, change line:
```nginx
fastcgi_pass php:9000;
```
to:
```nginx
fastcgi_pass 127.0.0.1:9000;
```

- [ ] **Step 2: Create nginx Dockerfile**

Create `docker/nginx/Dockerfile`:
```dockerfile
FROM nginx:alpine
COPY default.conf /etc/nginx/conf.d/default.conf
```

- [ ] **Step 3: Create php startup script**

Create `docker/php/start.sh`:
```bash
#!/bin/sh
set -e

echo "Running database migrations..."
php artisan migrate --force

echo "Starting php-fpm..."
exec php-fpm
```

- [ ] **Step 4: Make startup script executable and update Dockerfile CMD**

```bash
chmod +x docker/php/start.sh
```

In `docker/php/Dockerfile`, replace the final `CMD` line:
```dockerfile
# Replace:
CMD ["php-fpm"]

# With:
COPY docker/php/start.sh /usr/local/bin/start.sh
CMD ["/usr/local/bin/start.sh"]
```

- [ ] **Step 5: Add health check route**

In `routes/web.php`, add before the closing line:
```php
Route::get('/health', fn () => response()->json(['status' => 'ok']));
```

- [ ] **Step 6: Test local Docker build**

```bash
docker build -t test-app -f docker/php/Dockerfile .
docker build -t test-nginx -f docker/nginx/Dockerfile docker/nginx/
echo "Both images build successfully"
```

Expected: both builds complete with no errors.

- [ ] **Step 7: Commit**

```bash
git add docker/nginx/default.conf docker/nginx/Dockerfile docker/php/start.sh docker/php/Dockerfile routes/web.php
git commit -m "feat(deploy): prepare Docker images for ECS — localhost fastcgi, startup script, health route"
```

---

## Task 3: Terraform Bootstrap — S3 State Backend

**Files:** none (AWS resources created via CLI; Terraform init happens here)

Terraform state must live in S3 before any `terraform apply` can run. These resources are created manually once.

- [ ] **Step 1: Create S3 bucket for Terraform state**

```bash
aws s3api create-bucket \
  --bucket multitenant-sso-terraform-state \
  --region us-east-1

aws s3api put-bucket-versioning \
  --bucket multitenant-sso-terraform-state \
  --versioning-configuration Status=Enabled

aws s3api put-bucket-encryption \
  --bucket multitenant-sso-terraform-state \
  --server-side-encryption-configuration \
  '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'

aws s3api put-public-access-block \
  --bucket multitenant-sso-terraform-state \
  --public-access-block-configuration \
  "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

Expected: each command returns no output (success).

- [ ] **Step 2: Create DynamoDB table for state locking**

```bash
aws dynamodb create-table \
  --table-name multitenant-sso-terraform-locks \
  --attribute-definitions AttributeName=LockID,AttributeType=S \
  --key-schema AttributeName=LockID,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST \
  --region us-east-1
```

Expected: JSON output showing table status `CREATING` then `ACTIVE`.

- [ ] **Step 3: Create Terraform directory structure**

```bash
mkdir -p infrastructure/terraform
```

- [ ] **Step 4: Create main.tf**

Create `infrastructure/terraform/main.tf`:
```hcl
terraform {
  required_version = ">= 1.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  backend "s3" {
    bucket         = "multitenant-sso-terraform-state"
    key            = "production/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "multitenant-sso-terraform-locks"
    encrypt        = true
  }
}

provider "aws" {
  region = var.aws_region
}
```

- [ ] **Step 5: Create variables.tf**

Create `infrastructure/terraform/variables.tf`:
```hcl
variable "aws_region" {
  description = "AWS region"
  default     = "us-east-1"
}

variable "app_name" {
  description = "Application name used for resource naming"
  default     = "multitenant-sso"
}

variable "domain" {
  description = "Full subdomain for the application"
  default     = "multi-tenancy.ronmartdanieljavier.com"
}

variable "db_name" {
  description = "PostgreSQL database name"
  default     = "laravel"
}

variable "db_username" {
  description = "PostgreSQL username"
  default     = "laravel"
}
```

- [ ] **Step 6: Create outputs.tf**

Create `infrastructure/terraform/outputs.tf`:
```hcl
output "alb_dns_name" {
  description = "Add this as CNAME for your subdomain in A2Hosting after cert is validated"
  value       = aws_lb.main.dns_name
}

output "ecr_app_url" {
  description = "ECR URL for the PHP app image"
  value       = aws_ecr_repository.app.repository_url
}

output "ecr_nginx_url" {
  description = "ECR URL for the nginx image"
  value       = aws_ecr_repository.nginx.repository_url
}

output "acm_validation_cname" {
  description = "Add this CNAME in A2Hosting to validate the ACM SSL certificate"
  value       = tolist(aws_acm_certificate.main.domain_validation_options)[0]
}

output "github_actions_role_arn" {
  description = "Set this as AWS_ROLE_ARN secret in GitHub Actions"
  value       = aws_iam_role.github_actions.arn
}

output "secrets_manager_arn" {
  description = "ARN of the Secrets Manager secret — use to set values via CLI"
  value       = aws_secretsmanager_secret.app.arn
}

output "ecs_cluster_name" {
  value = aws_ecs_cluster.main.name
}

output "ecs_service_name" {
  value = aws_ecs_service.app.name
}
```

- [ ] **Step 7: Initialize Terraform**

```bash
cd infrastructure/terraform
terraform init
```

Expected output includes: `Terraform has been successfully initialized!`

- [ ] **Step 8: Commit**

```bash
cd ../..
git add infrastructure/terraform/main.tf infrastructure/terraform/variables.tf infrastructure/terraform/outputs.tf
git commit -m "feat(deploy): add Terraform bootstrap with S3 backend"
```

---

## Task 4: VPC, Security Groups, and ECR

**Files:**
- Create: `infrastructure/terraform/vpc.tf`
- Create: `infrastructure/terraform/ecr.tf`

ECS tasks run in **public subnets** with `assign_public_ip = true` (avoids ~$32/month NAT Gateway cost). Security groups restrict all ingress: only the ALB can reach the app on port 80.

- [ ] **Step 1: Create vpc.tf**

Create `infrastructure/terraform/vpc.tf`:
```hcl
resource "aws_vpc" "main" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true
  tags = { Name = "${var.app_name}-vpc" }
}

resource "aws_subnet" "public_a" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.0.1.0/24"
  availability_zone       = "${var.aws_region}a"
  map_public_ip_on_launch = true
  tags = { Name = "${var.app_name}-public-a" }
}

resource "aws_subnet" "public_b" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.0.2.0/24"
  availability_zone       = "${var.aws_region}b"
  map_public_ip_on_launch = true
  tags = { Name = "${var.app_name}-public-b" }
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
  tags   = { Name = "${var.app_name}-igw" }
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
  tags = { Name = "${var.app_name}-rt-public" }
}

resource "aws_route_table_association" "public_a" {
  subnet_id      = aws_subnet.public_a.id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table_association" "public_b" {
  subnet_id      = aws_subnet.public_b.id
  route_table_id = aws_route_table.public.id
}

# ALB security group: accepts HTTPS and HTTP from the internet
resource "aws_security_group" "alb" {
  name   = "${var.app_name}-alb"
  vpc_id = aws_vpc.main.id

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.app_name}-alb-sg" }
}

# App security group: accepts port 80 only from ALB
resource "aws_security_group" "app" {
  name   = "${var.app_name}-app"
  vpc_id = aws_vpc.main.id

  ingress {
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.app_name}-app-sg" }
}

# EFS security group: accepts NFS (2049) only from app tasks
resource "aws_security_group" "efs" {
  name   = "${var.app_name}-efs"
  vpc_id = aws_vpc.main.id

  ingress {
    from_port       = 2049
    to_port         = 2049
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.app_name}-efs-sg" }
}
```

- [ ] **Step 2: Create ecr.tf**

Create `infrastructure/terraform/ecr.tf`:
```hcl
resource "aws_ecr_repository" "app" {
  name                 = "${var.app_name}-app"
  image_tag_mutability = "MUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }

  tags = { Name = "${var.app_name}-app" }
}

resource "aws_ecr_repository" "nginx" {
  name                 = "${var.app_name}-nginx"
  image_tag_mutability = "MUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }

  tags = { Name = "${var.app_name}-nginx" }
}

# Keep only the 5 most recent images to control storage costs
resource "aws_ecr_lifecycle_policy" "app" {
  repository = aws_ecr_repository.app.name
  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Keep last 5 images"
      selection = {
        tagStatus   = "any"
        countType   = "imageCountMoreThan"
        countNumber = 5
      }
      action = { type = "expire" }
    }]
  })
}

resource "aws_ecr_lifecycle_policy" "nginx" {
  repository = aws_ecr_repository.nginx.name
  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Keep last 5 images"
      selection = {
        tagStatus   = "any"
        countType   = "imageCountMoreThan"
        countNumber = 5
      }
      action = { type = "expire" }
    }]
  })
}
```

- [ ] **Step 3: Apply and verify**

```bash
cd infrastructure/terraform
terraform plan -out=tfplan
terraform apply tfplan
```

Expected: VPC, 2 subnets, IGW, route table, 3 security groups, 2 ECR repos created. ~15 resources.

- [ ] **Step 4: Commit**

```bash
cd ../..
git add infrastructure/terraform/vpc.tf infrastructure/terraform/ecr.tf
git commit -m "feat(deploy): add VPC, security groups, and ECR repositories"
```

---

## Task 5: EFS, IAM, and Secrets Manager

**Files:**
- Create: `infrastructure/terraform/efs.tf`
- Create: `infrastructure/terraform/iam.tf`
- Create: `infrastructure/terraform/secrets.tf`

- [ ] **Step 1: Create efs.tf**

Create `infrastructure/terraform/efs.tf`:
```hcl
resource "aws_efs_file_system" "main" {
  creation_token = var.app_name
  encrypted      = true
  tags           = { Name = var.app_name }
}

resource "aws_efs_mount_target" "a" {
  file_system_id  = aws_efs_file_system.main.id
  subnet_id       = aws_subnet.public_a.id
  security_groups = [aws_security_group.efs.id]
}

resource "aws_efs_mount_target" "b" {
  file_system_id  = aws_efs_file_system.main.id
  subnet_id       = aws_subnet.public_b.id
  security_groups = [aws_security_group.efs.id]
}

# Access point for PostgreSQL data (UID 999 = postgres user in Alpine)
resource "aws_efs_access_point" "postgres" {
  file_system_id = aws_efs_file_system.main.id

  posix_user {
    uid = 999
    gid = 999
  }

  root_directory {
    path = "/postgres"
    creation_info {
      owner_uid   = 999
      owner_gid   = 999
      permissions = "755"
    }
  }

  tags = { Name = "${var.app_name}-postgres" }
}

# Access point for Redis AOF data (UID 999 = redis user in Alpine)
resource "aws_efs_access_point" "redis" {
  file_system_id = aws_efs_file_system.main.id

  posix_user {
    uid = 999
    gid = 999
  }

  root_directory {
    path = "/redis"
    creation_info {
      owner_uid   = 999
      owner_gid   = 999
      permissions = "755"
    }
  }

  tags = { Name = "${var.app_name}-redis" }
}

# Access point for Laravel storage/ (UID 82 = www-data in Alpine)
resource "aws_efs_access_point" "storage" {
  file_system_id = aws_efs_file_system.main.id

  posix_user {
    uid = 82
    gid = 82
  }

  root_directory {
    path = "/storage"
    creation_info {
      owner_uid   = 82
      owner_gid   = 82
      permissions = "755"
    }
  }

  tags = { Name = "${var.app_name}-storage" }
}
```

- [ ] **Step 2: Create secrets.tf**

Terraform creates the secret shell; values are set via AWS CLI in Task 9 (never stored in Terraform state).

Create `infrastructure/terraform/secrets.tf`:
```hcl
resource "aws_secretsmanager_secret" "app" {
  name                    = "${var.app_name}/app"
  recovery_window_in_days = 0
  tags                    = { Name = var.app_name }
}
```

- [ ] **Step 3: Create iam.tf**

Create `infrastructure/terraform/iam.tf`:
```hcl
# ECS Task Execution Role — used by ECS to pull images and read secrets
resource "aws_iam_role" "ecs_execution" {
  name = "${var.app_name}-ecs-execution"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "ecs-tasks.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })
}

resource "aws_iam_role_policy_attachment" "ecs_execution_base" {
  role       = aws_iam_role.ecs_execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role_policy" "ecs_execution_secrets" {
  name = "secrets-access"
  role = aws_iam_role.ecs_execution.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["secretsmanager:GetSecretValue"]
      Resource = [aws_secretsmanager_secret.app.arn]
    }]
  })
}

# ECS Task Role — used by application code at runtime
resource "aws_iam_role" "ecs_task" {
  name = "${var.app_name}-ecs-task"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "ecs-tasks.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })
}

# GitHub Actions OIDC — allows GitHub to assume an AWS role without stored keys
data "aws_caller_identity" "current" {}

resource "aws_iam_openid_connect_provider" "github" {
  url             = "https://token.actions.githubusercontent.com"
  client_id_list  = ["sts.amazonaws.com"]
  thumbprint_list = ["6938fd4d98bab03faadb97b34396831e3780aea1"]
}

resource "aws_iam_role" "github_actions" {
  name = "${var.app_name}-github-actions"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect = "Allow"
      Principal = {
        Federated = aws_iam_openid_connect_provider.github.arn
      }
      Action = "sts:AssumeRoleWithWebIdentity"
      Condition = {
        StringEquals = {
          "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
        }
        StringLike = {
          "token.actions.githubusercontent.com:sub" = "repo:ronmartdanieljavier/laravel-multitenant-sso-boilerplate:*"
        }
      }
    }]
  })
}

resource "aws_iam_role_policy" "github_actions_deploy" {
  name = "deploy"
  role = aws_iam_role.github_actions.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "ecr:GetAuthorizationToken",
          "ecr:BatchCheckLayerAvailability",
          "ecr:PutImage",
          "ecr:InitiateLayerUpload",
          "ecr:UploadLayerPart",
          "ecr:CompleteLayerUpload",
          "ecr:BatchGetImage"
        ]
        Resource = "*"
      },
      {
        Effect = "Allow"
        Action = [
          "ecs:RegisterTaskDefinition",
          "ecs:UpdateService",
          "ecs:DescribeServices",
          "ecs:DescribeTaskDefinition"
        ]
        Resource = "*"
      },
      {
        Effect   = "Allow"
        Action   = ["iam:PassRole"]
        Resource = [
          aws_iam_role.ecs_execution.arn,
          aws_iam_role.ecs_task.arn
        ]
      }
    ]
  })
}
```

- [ ] **Step 4: Apply**

```bash
cd infrastructure/terraform
terraform apply -auto-approve
```

Expected: EFS filesystem + 2 mount targets + 3 access points, Secrets Manager secret, 3 IAM roles + policies created.

- [ ] **Step 5: Commit**

```bash
cd ../..
git add infrastructure/terraform/efs.tf infrastructure/terraform/iam.tf infrastructure/terraform/secrets.tf
git commit -m "feat(deploy): add EFS volumes, IAM roles, and Secrets Manager"
```

---

## Task 6: ECS Cluster, Task Definition, and Service

**Files:**
- Create: `infrastructure/terraform/ecs.tf`

All 5 containers run in a single Fargate task sharing `localhost`. Horizon is `essential = false` so it can crash/restart without killing the whole task.

- [ ] **Step 1: Create ecs.tf**

Create `infrastructure/terraform/ecs.tf`:
```hcl
resource "aws_ecs_cluster" "main" {
  name = var.app_name

  setting {
    name  = "containerInsights"
    value = "disabled"
  }
}

resource "aws_cloudwatch_log_group" "app" {
  name              = "/ecs/${var.app_name}"
  retention_in_days = 7
}

locals {
  app_image   = "${aws_ecr_repository.app.repository_url}:latest"
  nginx_image = "${aws_ecr_repository.nginx.repository_url}:latest"
}

resource "aws_ecs_task_definition" "app" {
  family                   = var.app_name
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "1024"
  memory                   = "2048"
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  volume {
    name = "postgres-data"
    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.main.id
      transit_encryption = "ENABLED"
      authorization_config {
        access_point_id = aws_efs_access_point.postgres.id
        iam             = "DISABLED"
      }
    }
  }

  volume {
    name = "redis-data"
    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.main.id
      transit_encryption = "ENABLED"
      authorization_config {
        access_point_id = aws_efs_access_point.redis.id
        iam             = "DISABLED"
      }
    }
  }

  volume {
    name = "app-storage"
    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.main.id
      transit_encryption = "ENABLED"
      authorization_config {
        access_point_id = aws_efs_access_point.storage.id
        iam             = "DISABLED"
      }
    }
  }

  container_definitions = jsonencode([
    {
      name      = "postgres"
      image     = "postgres:17-alpine"
      essential = true
      environment = [
        { name = "POSTGRES_DB",   value = var.db_name },
        { name = "POSTGRES_USER", value = var.db_username }
      ]
      secrets = [
        { name = "POSTGRES_PASSWORD", valueFrom = "${aws_secretsmanager_secret.app.arn}:db_password::" }
      ]
      mountPoints = [{
        sourceVolume  = "postgres-data"
        containerPath = "/var/lib/postgresql/data"
        readOnly      = false
      }]
      healthCheck = {
        command     = ["CMD-SHELL", "pg_isready -U ${var.db_username} -d ${var.db_name}"]
        interval    = 10
        timeout     = 5
        retries     = 5
        startPeriod = 30
      }
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/${var.app_name}"
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "postgres"
        }
      }
    },
    {
      name      = "redis"
      image     = "redis:7-alpine"
      essential = true
      command   = ["redis-server", "--appendonly", "yes"]
      mountPoints = [{
        sourceVolume  = "redis-data"
        containerPath = "/data"
        readOnly      = false
      }]
      healthCheck = {
        command     = ["CMD", "redis-cli", "ping"]
        interval    = 10
        timeout     = 5
        retries     = 5
        startPeriod = 10
      }
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/${var.app_name}"
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "redis"
        }
      }
    },
    {
      name      = "php"
      image     = local.app_image
      essential = true
      dependsOn = [
        { containerName = "postgres", condition = "HEALTHY" },
        { containerName = "redis",    condition = "HEALTHY" }
      ]
      secrets = [
        { name = "APP_KEY",     valueFrom = "${aws_secretsmanager_secret.app.arn}:app_key::" },
        { name = "DB_PASSWORD", valueFrom = "${aws_secretsmanager_secret.app.arn}:db_password::" }
      ]
      environment = [
        { name = "APP_ENV",          value = "production" },
        { name = "APP_DEBUG",        value = "false" },
        { name = "APP_URL",          value = "https://${var.domain}" },
        { name = "DB_CONNECTION",    value = "pgsql" },
        { name = "DB_HOST",          value = "127.0.0.1" },
        { name = "DB_PORT",          value = "5432" },
        { name = "DB_DATABASE",      value = var.db_name },
        { name = "DB_USERNAME",      value = var.db_username },
        { name = "REDIS_HOST",       value = "127.0.0.1" },
        { name = "REDIS_PORT",       value = "6379" },
        { name = "CACHE_STORE",      value = "redis" },
        { name = "SESSION_DRIVER",   value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "LOG_CHANNEL",      value = "stderr" }
      ]
      mountPoints = [{
        sourceVolume  = "app-storage"
        containerPath = "/var/www/html/storage"
        readOnly      = false
      }]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/${var.app_name}"
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "php"
        }
      }
    },
    {
      name      = "nginx"
      image     = local.nginx_image
      essential = true
      portMappings = [{
        containerPort = 80
        protocol      = "tcp"
      }]
      dependsOn = [
        { containerName = "php", condition = "START" }
      ]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/${var.app_name}"
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "nginx"
        }
      }
    },
    {
      name      = "horizon"
      image     = local.app_image
      essential = false
      command   = ["php", "artisan", "horizon"]
      dependsOn = [
        { containerName = "php", condition = "START" }
      ]
      secrets = [
        { name = "APP_KEY",     valueFrom = "${aws_secretsmanager_secret.app.arn}:app_key::" },
        { name = "DB_PASSWORD", valueFrom = "${aws_secretsmanager_secret.app.arn}:db_password::" }
      ]
      environment = [
        { name = "APP_ENV",          value = "production" },
        { name = "APP_DEBUG",        value = "false" },
        { name = "APP_URL",          value = "https://${var.domain}" },
        { name = "DB_CONNECTION",    value = "pgsql" },
        { name = "DB_HOST",          value = "127.0.0.1" },
        { name = "DB_PORT",          value = "5432" },
        { name = "DB_DATABASE",      value = var.db_name },
        { name = "DB_USERNAME",      value = var.db_username },
        { name = "REDIS_HOST",       value = "127.0.0.1" },
        { name = "REDIS_PORT",       value = "6379" },
        { name = "CACHE_STORE",      value = "redis" },
        { name = "SESSION_DRIVER",   value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "LOG_CHANNEL",      value = "stderr" }
      ]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/${var.app_name}"
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "horizon"
        }
      }
    }
  ])
}

resource "aws_ecs_service" "app" {
  name            = var.app_name
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = [aws_subnet.public_a.id, aws_subnet.public_b.id]
    security_groups  = [aws_security_group.app.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "nginx"
    container_port   = 80
  }

  # Prevent Terraform from overwriting task definition after GitHub Actions deploys a new revision
  lifecycle {
    ignore_changes = [task_definition]
  }

  depends_on = [aws_lb_listener.https]
}
```

- [ ] **Step 2: Commit (do not apply yet — ALB is referenced and defined in Task 7)**

```bash
cd ../..
git add infrastructure/terraform/ecs.tf
git commit -m "feat(deploy): add ECS cluster, task definition, and service"
```

---

## Task 7: ALB, ACM Certificate, and DNS

**Files:**
- Create: `infrastructure/terraform/alb.tf`

The HTTPS listener depends on ACM cert validation. We apply in two stages: first create the cert (to get the CNAME), then add the CNAME in A2Hosting, then apply the rest.

- [ ] **Step 1: Create alb.tf**

Create `infrastructure/terraform/alb.tf`:
```hcl
resource "aws_lb" "main" {
  name               = var.app_name
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = [aws_subnet.public_a.id, aws_subnet.public_b.id]

  tags = { Name = var.app_name }
}

resource "aws_lb_target_group" "app" {
  name        = var.app_name
  port        = 80
  protocol    = "HTTP"
  vpc_id      = aws_vpc.main.id
  target_type = "ip"

  health_check {
    enabled             = true
    path                = "/health"
    healthy_threshold   = 2
    unhealthy_threshold = 5
    timeout             = 5
    interval            = 30
    matcher             = "200"
  }

  tags = { Name = var.app_name }
}

# Redirect HTTP → HTTPS
resource "aws_lb_listener" "http_redirect" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"
    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

resource "aws_acm_certificate" "main" {
  domain_name       = var.domain
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }

  tags = { Name = var.app_name }
}

resource "aws_acm_certificate_validation" "main" {
  certificate_arn = aws_acm_certificate.main.arn

  timeouts {
    create = "30m"
  }
}

resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.main.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = aws_acm_certificate_validation.main.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app.arn
  }
}
```

- [ ] **Step 2: Apply only the ACM certificate first**

```bash
cd infrastructure/terraform
terraform apply -target=aws_acm_certificate.main -auto-approve
```

Expected: ACM certificate created in PENDING_VALIDATION state.

- [ ] **Step 3: Get the validation CNAME record**

```bash
terraform output acm_validation_cname
```

Expected output (example):
```
{
  "domain_name"          = "_abc123.multi-tenancy.ronmartdanieljavier.com."
  "resource_record_name" = "_abc123.multi-tenancy.ronmartdanieljavier.com."
  "resource_record_type" = "CNAME"
  "resource_record_value" = "_xyz789.acm-validations.aws."
}
```

- [ ] **Step 4: Add validation CNAME in A2Hosting**

1. Log in to A2Hosting control panel → cPanel → Zone Editor (or DNS Zone Editor)
2. Find `ronmartdanieljavier.com`
3. Add CNAME record:
   - **Name:** the `resource_record_name` value from above (e.g., `_abc123.multi-tenancy`)
   - **Value:** the `resource_record_value` value from above (e.g., `_xyz789.acm-validations.aws.`)
4. Save. DNS propagation takes 2–10 minutes.

- [ ] **Step 5: Apply everything**

Wait ~5 minutes for DNS to propagate, then:
```bash
terraform apply -auto-approve
```

This creates: ALB, target group, HTTP redirect listener, ACM validation (waits up to 30 min for cert), HTTPS listener, ECS service. ~20 resources.

Expected final output:
```
alb_dns_name = "multitenant-sso-xxxx.us-east-1.elb.amazonaws.com"
github_actions_role_arn = "arn:aws:iam::123456789012:role/multitenant-sso-github-actions"
ecr_app_url = "123456789012.dkr.ecr.us-east-1.amazonaws.com/multitenant-sso-app"
ecr_nginx_url = "123456789012.dkr.ecr.us-east-1.amazonaws.com/multitenant-sso-nginx"
```

- [ ] **Step 6: Add app CNAME in A2Hosting**

In A2Hosting DNS Zone Editor, add a second CNAME:
- **Name:** `multi-tenancy`
- **Value:** the `alb_dns_name` from Terraform output (e.g., `multitenant-sso-xxxx.us-east-1.elb.amazonaws.com`)

- [ ] **Step 7: Commit**

```bash
cd ../..
git add infrastructure/terraform/alb.tf
git commit -m "feat(deploy): add ALB, ACM certificate, and HTTPS listener"
```

---

## Task 8: Set App Secrets in AWS Secrets Manager

**Files:** none (AWS CLI commands only)

Secrets are stored as a single JSON object in Secrets Manager. Never commit these values to git.

- [ ] **Step 1: Generate a Laravel app key locally**

```bash
php artisan key:generate --show
# Copy the output, e.g.: base64:abc123...
```

- [ ] **Step 2: Choose a strong database password**

```bash
openssl rand -base64 32
# Copy the output
```

- [ ] **Step 3: Store secrets in Secrets Manager**

Replace `<APP_KEY>` and `<DB_PASSWORD>` with your actual values:

```bash
aws secretsmanager put-secret-value \
  --secret-id "multitenant-sso/app" \
  --secret-string '{
    "app_key": "<APP_KEY>",
    "db_password": "<DB_PASSWORD>"
  }' \
  --region us-east-1
```

Expected: JSON response with `VersionId`.

- [ ] **Step 4: Verify the secret is readable**

```bash
aws secretsmanager get-secret-value \
  --secret-id "multitenant-sso/app" \
  --query SecretString \
  --output text \
  --region us-east-1
```

Expected: JSON with both keys present.

---

## Task 9: Push Initial Docker Images to ECR

The ECS service needs at least one image in ECR before the first task can start.

- [ ] **Step 1: Authenticate Docker to ECR**

```bash
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  $(aws sts get-caller-identity --query Account --output text).dkr.ecr.us-east-1.amazonaws.com
```

Expected: `Login Succeeded`

- [ ] **Step 2: Get ECR URLs from Terraform**

```bash
cd infrastructure/terraform
ECR_APP=$(terraform output -raw ecr_app_url)
ECR_NGINX=$(terraform output -raw ecr_nginx_url)
cd ../..
echo "App: $ECR_APP"
echo "Nginx: $ECR_NGINX"
```

- [ ] **Step 3: Build and push app image**

```bash
docker build -t $ECR_APP:latest -f docker/php/Dockerfile .
docker push $ECR_APP:latest
```

Expected: image pushed, you see layer digests.

- [ ] **Step 4: Build and push nginx image**

```bash
docker build -t $ECR_NGINX:latest -f docker/nginx/Dockerfile docker/nginx/
docker push $ECR_NGINX:latest
```

Expected: image pushed.

- [ ] **Step 5: Force ECS to start the service**

```bash
CLUSTER=$(cd infrastructure/terraform && terraform output -raw ecs_cluster_name)
SERVICE=$(cd infrastructure/terraform && terraform output -raw ecs_service_name)

aws ecs update-service \
  --cluster $CLUSTER \
  --service $SERVICE \
  --force-new-deployment \
  --region us-east-1

echo "Waiting for service to stabilize (this takes 2–5 minutes)..."
aws ecs wait services-stable \
  --cluster $CLUSTER \
  --services $SERVICE \
  --region us-east-1

echo "Service is stable"
```

- [ ] **Step 6: Verify health check**

```bash
ALB_DNS=$(cd infrastructure/terraform && terraform output -raw alb_dns_name)
curl -I http://$ALB_DNS/health
```

Expected: `HTTP/1.1 200 OK` (HTTP, since HTTPS CNAME may not be propagated yet).

Once DNS propagates (~5–10 minutes after adding CNAME in Task 7 Step 6):
```bash
curl -I https://multi-tenancy.ronmartdanieljavier.com/health
```
Expected: `HTTP/1.1 200 OK`

---

## Task 10: GitHub Actions CI/CD Workflow

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create .github/workflows directory**

```bash
mkdir -p .github/workflows
```

- [ ] **Step 2: Create ci.yml**

Create `.github/workflows/ci.yml`:
```yaml
name: CI/CD

on:
  push:
    branches: [main]
  pull_request:

permissions:
  id-token: write   # required for OIDC
  contents: read

jobs:
  test:
    name: Test
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:17-alpine
        env:
          POSTGRES_DB: laravel_test
          POSTGRES_USER: laravel
          POSTGRES_PASSWORD: secret
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 5s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 5s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.5
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: pdo_pgsql, redis, zip, bcmath, intl, pcntl, gd
          coverage: none

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Prepare .env.testing
        run: |
          cp .env.example .env.testing
          sed -i 's|DB_CONNECTION=sqlite|DB_CONNECTION=pgsql|' .env.testing
          echo "DB_HOST=127.0.0.1" >> .env.testing
          echo "DB_PORT=5432" >> .env.testing
          echo "DB_DATABASE=laravel_test" >> .env.testing
          echo "DB_USERNAME=laravel" >> .env.testing
          echo "DB_PASSWORD=secret" >> .env.testing
          echo "REDIS_HOST=127.0.0.1" >> .env.testing
          echo "REDIS_PORT=6379" >> .env.testing
          echo "CACHE_STORE=redis" >> .env.testing
          echo "SESSION_DRIVER=redis" >> .env.testing
          echo "QUEUE_CONNECTION=redis" >> .env.testing

      - name: Generate app key
        run: php artisan key:generate --env=testing

      - name: Run migrations
        run: php artisan migrate --env=testing --force

      - name: Run test suite
        run: php artisan test

  deploy:
    name: Deploy to ECS
    needs: test
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Configure AWS credentials via OIDC
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_ROLE_ARN }}
          aws-region: us-east-1

      - name: Login to Amazon ECR
        id: login-ecr
        uses: aws-actions/amazon-ecr-login@v2

      - name: Build and push PHP app image
        env:
          ECR_APP: ${{ secrets.ECR_APP_REPOSITORY }}
          IMAGE_TAG: ${{ github.sha }}
        run: |
          docker build \
            -t $ECR_APP:$IMAGE_TAG \
            -t $ECR_APP:latest \
            -f docker/php/Dockerfile .
          docker push $ECR_APP:$IMAGE_TAG
          docker push $ECR_APP:latest

      - name: Build and push nginx image
        env:
          ECR_NGINX: ${{ secrets.ECR_NGINX_REPOSITORY }}
          IMAGE_TAG: ${{ github.sha }}
        run: |
          docker build \
            -t $ECR_NGINX:$IMAGE_TAG \
            -t $ECR_NGINX:latest \
            -f docker/nginx/Dockerfile docker/nginx/
          docker push $ECR_NGINX:$IMAGE_TAG
          docker push $ECR_NGINX:latest

      - name: Deploy new revision to ECS
        env:
          CLUSTER: ${{ secrets.ECS_CLUSTER }}
          SERVICE: ${{ secrets.ECS_SERVICE }}
        run: |
          aws ecs update-service \
            --cluster $CLUSTER \
            --service $SERVICE \
            --force-new-deployment \
            --region us-east-1

      - name: Wait for deployment to stabilize
        env:
          CLUSTER: ${{ secrets.ECS_CLUSTER }}
          SERVICE: ${{ secrets.ECS_SERVICE }}
        run: |
          aws ecs wait services-stable \
            --cluster $CLUSTER \
            --services $SERVICE \
            --region us-east-1
```

- [ ] **Step 3: Add GitHub Actions secrets**

In your GitHub repo → Settings → Secrets and variables → Actions → New repository secret. Add each:

| Secret name | Where to get the value |
|---|---|
| `AWS_ROLE_ARN` | `cd infrastructure/terraform && terraform output github_actions_role_arn` |
| `ECR_APP_REPOSITORY` | `cd infrastructure/terraform && terraform output ecr_app_url` |
| `ECR_NGINX_REPOSITORY` | `cd infrastructure/terraform && terraform output ecr_nginx_url` |
| `ECS_CLUSTER` | `cd infrastructure/terraform && terraform output ecs_cluster_name` |
| `ECS_SERVICE` | `cd infrastructure/terraform && terraform output ecs_service_name` |

- [ ] **Step 4: Commit and push**

```bash
git add .github/workflows/ci.yml
git commit -m "feat(deploy): add GitHub Actions CI/CD workflow for ECS"
git push origin main
```

- [ ] **Step 5: Verify the workflow runs**

Go to your GitHub repo → Actions tab. You should see the `CI/CD` workflow triggered. The `test` job runs first; `deploy` runs after it passes.

Expected: both jobs green. ECS rolls out new task revision with the freshly pushed images.

---

## Task 11: Verify End-to-End

- [ ] **Step 1: Check ECS service health**

```bash
aws ecs describe-services \
  --cluster multitenant-sso \
  --services multitenant-sso \
  --query 'services[0].{running:runningCount,desired:desiredCount,deployments:deployments[*].{status:status,running:runningCount}}' \
  --output json \
  --region us-east-1
```

Expected: `runningCount: 1`, `desiredCount: 1`, one deployment with `status: PRIMARY`.

- [ ] **Step 2: Check application is reachable**

```bash
curl -s https://multi-tenancy.ronmartdanieljavier.com/health
```

Expected: `{"status":"ok"}`

- [ ] **Step 3: Check CloudWatch logs for errors**

```bash
aws logs tail /ecs/multitenant-sso --follow --region us-east-1
```

Look for any PHP errors or migration failures. Press Ctrl+C to stop.

- [ ] **Step 4: (Optional) Delete the bootstrap IAM user**

Once GitHub Actions is deploying successfully via OIDC, delete the `terraform-bootstrap` IAM user created in Task 1 (it was only needed for initial setup):

```bash
# List and delete access keys first
aws iam list-access-keys --user-name terraform-bootstrap
aws iam delete-access-key --user-name terraform-bootstrap --access-key-id <KEY_ID>
aws iam detach-user-policy --user-name terraform-bootstrap \
  --policy-arn arn:aws:iam::aws:policy/AdministratorAccess
aws iam delete-user --user-name terraform-bootstrap
```
