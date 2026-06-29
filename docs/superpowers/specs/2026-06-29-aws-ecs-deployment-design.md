# AWS ECS Deployment Design

**Date:** 2026-06-29
**Domain:** multi-tenancy.ronmartdanieljavier.com
**DNS Provider:** A2Hosting
**Routing:** Single domain (no wildcard subdomains)

---

## Overview

Deploy the Laravel multi-tenant SSO boilerplate to AWS ECS Fargate using the existing Docker setup. GitHub Actions handles CI/CD. Containers (PostgreSQL, Redis) use EFS for persistence instead of managed AWS services to minimise cost.

---

## Infrastructure

### AWS Services

| Service | Purpose | Est. Cost |
|---|---|---|
| ECS Fargate | Run PHP, Nginx, Horizon, Postgres, Redis containers | ~$15–30/mo |
| ECR | Docker image registry | ~$0 (500MB free) |
| ALB | HTTPS load balancer, SSL termination | ~$16/mo |
| ACM | SSL certificate for subdomain | Free |
| EFS | Persistent volumes for Postgres data, Redis data, Laravel storage/ | ~$0 (5GB free) |
| VPC | Private networking | Free |
| Secrets Manager | App secrets (APP_KEY, DB_PASSWORD, etc.) | ~$0.40/secret/mo |

**Rough total: $30–50/month**

### ECS Task Definitions

Four ECS services run in the same VPC, connected via a private security group:

| Service | Containers | Resources |
|---|---|---|
| `app` | nginx + php-fpm | 0.25 vCPU / 0.5GB |
| `horizon` | php (artisan horizon) | 0.25 vCPU / 0.5GB |
| `postgres` | postgres:17-alpine | 0.25 vCPU / 0.5GB + EFS mount |
| `redis` | redis:7-alpine | 0.25 vCPU / 0.5GB + EFS mount |

### Networking

```
Internet
  └── ALB (port 443, ACM cert)
        └── Target Group → ECS app service (port 80, nginx)
              └── VPC private subnet
                    ├── app service (nginx → php-fpm via Unix socket)
                    ├── horizon service
                    ├── postgres service (EFS: /var/lib/postgresql/data)
                    └── redis service (EFS: /data)
```

Security groups:
- ALB SG: inbound 443 from 0.0.0.0/0
- App SG: inbound 80 from ALB SG only
- DB SG: inbound 5432/6379 from App SG only

### EFS Mounts

| Mount Point in Container | EFS Access Point | Purpose |
|---|---|---|
| `/var/lib/postgresql/data` | `/postgres` | PostgreSQL data |
| `/data` | `/redis` | Redis AOF/RDB |
| `/var/www/html/storage` | `/app-storage` | Laravel storage (logs, uploads) |

---

## DNS & SSL

1. Request ACM certificate for `multi-tenancy.ronmartdanieljavier.com` (DNS validation)
2. Add ACM validation CNAME record in A2Hosting DNS panel
3. ACM issues certificate automatically
4. After ALB is provisioned, add CNAME in A2Hosting:
   - `multi-tenancy.ronmartdanieljavier.com` → `<alb-name>.us-east-1.elb.amazonaws.com`

---

## CI/CD — GitHub Actions

### Workflow Triggers

- **On pull request:** run tests only
- **On push to `main`:** run tests, then deploy

### Job 1: Test

```yaml
services:
  postgres: postgres:17
  redis: redis:7
steps:
  - composer install
  - php artisan migrate
  - php artisan test --compact
```

### Job 2: Deploy (after tests pass, main branch only)

1. Authenticate to AWS via OIDC (no long-lived AWS access keys in GitHub)
2. Build Docker image from `docker/php/Dockerfile`
3. Push image to ECR with tag `sha-<git-sha>` and `latest`
4. Render new ECS task definition revision (update image URI)
5. `aws ecs update-service --force-new-deployment` → rolling update

### GitHub Secrets Required

| Secret | Value |
|---|---|
| `AWS_ROLE_ARN` | IAM role ARN (OIDC trust) |
| `ECR_REPOSITORY` | ECR repo URI |
| `ECS_CLUSTER` | ECS cluster name |
| `ECS_SERVICE_APP` | ECS app service name |
| `ECS_SERVICE_HORIZON` | ECS horizon service name |

App secrets (`APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, etc.) are stored in **AWS Secrets Manager** and injected into ECS task definitions at runtime — they are never stored in GitHub.

---

## Step-by-Step Provisioning Order

1. Create VPC (2 public subnets, 2 private subnets, IGW, NAT Gateway)
2. Create security groups (ALB, app, db)
3. Create ECR repository
4. Create EFS filesystem + access points (postgres, redis, app-storage)
5. Store app secrets in AWS Secrets Manager
6. Create ECS cluster
7. Create task definitions (postgres, redis, app, horizon)
8. Create ECS services (postgres → redis → app → horizon)
9. Create ALB + target group + listener (port 443)
10. Request ACM certificate → add validation CNAME in A2Hosting
11. Attach ACM cert to ALB HTTPS listener
12. Add app CNAME in A2Hosting → ALB DNS name
13. Create IAM OIDC provider for GitHub Actions
14. Create IAM role with ECR push + ECS deploy permissions
15. Add GitHub Actions workflow files
16. Push to `main` → first automated deploy

---

## IAM Role for GitHub Actions (OIDC)

Permissions needed:
- `ecr:GetAuthorizationToken`, `ecr:BatchCheckLayerAvailability`, `ecr:PutImage`, `ecr:InitiateLayerUpload`, `ecr:UploadLayerPart`, `ecr:CompleteLayerUpload`
- `ecs:RegisterTaskDefinition`, `ecs:UpdateService`, `ecs:DescribeServices`, `ecs:DescribeTaskDefinition`
- `iam:PassRole` (for ECS task execution role)
- `secretsmanager:GetSecretValue` (scoped to app secrets only)
