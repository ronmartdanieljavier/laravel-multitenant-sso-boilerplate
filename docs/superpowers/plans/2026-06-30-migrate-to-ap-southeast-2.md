# Migrate to ap-southeast-2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Destroy all AWS infrastructure in us-east-1 and recreate it identically in ap-southeast-2 (Sydney) for both staging and production environments.

**Architecture:** Terraform manages all AWS resources parameterised via `var.aws_region`. The migration destroys existing state, provisions a new Terraform backend in ap-southeast-2, then re-applies the unchanged resource definitions against the new region. CI/CD workflow hardcodes are updated last so no deploy fires until infrastructure is ready.

**Tech Stack:** Terraform ≥ 1.6, AWS CLI v2, GitHub Actions

## Global Constraints

- Region target: `ap-southeast-2`
- Both staging (`staging.multi-tenancy.ronmartdanieljavier.com`) and production (`multi-tenancy.ronmartdanieljavier.com`) must be covered
- New Terraform state bucket name: `multitenant-sso-terraform-state-apse2`
- DynamoDB lock table name: `multitenant-sso-terraform-locks` (same name, new region)
- All Terraform commands run from `infrastructure/terraform/`
- AWS CLI must be authenticated with an account that has admin/broad permissions (not the limited GitHub Actions OIDC role)

---

### Task 1: Destroy us-east-1 infrastructure

**Files:**
- No file changes — this task runs Terraform against the existing state

**Interfaces:**
- Produces: empty AWS account (no resources in us-east-1 under this project)

- [ ] **Step 1: Confirm current Terraform state backend is reachable**

```bash
cd infrastructure/terraform
terraform init -reconfigure \
  -backend-config="bucket=multitenant-sso-terraform-state" \
  -backend-config="key=production/terraform.tfstate" \
  -backend-config="region=us-east-1" \
  -backend-config="dynamodb_table=multitenant-sso-terraform-locks" \
  -backend-config="encrypt=true"
```

Expected: `Terraform has been successfully initialized!`

- [ ] **Step 2: Preview what will be destroyed**

```bash
terraform plan -destroy
```

Review the output. Confirm it lists ECR repos, ECS cluster, ECS services, ALB, EFS, VPC, IAM roles, Secrets Manager secrets, and ACM certs.

- [ ] **Step 3: Destroy all us-east-1 resources**

```bash
terraform destroy -auto-approve
```

Expected: `Destroy complete! Resources: N destroyed.`

This takes 5–15 minutes. ACM certs and ECS services are the slowest.

- [ ] **Step 4: Verify no resources remain**

Check AWS Console → us-east-1 for:
- ECS: no cluster named `multitenant-sso`
- ECR: no repos named `multitenant-sso-app` or `multitenant-sso-nginx`
- ALB: no load balancer named `multitenant-sso`
- EFS: no file system tagged `multitenant-sso`

---

### Task 2: Bootstrap ap-southeast-2 Terraform state backend

**Files:**
- No Terraform file changes — AWS resources created via CLI

**Interfaces:**
- Produces: S3 bucket `multitenant-sso-terraform-state-apse2` and DynamoDB table `multitenant-sso-terraform-locks` in ap-southeast-2, ready to serve as Terraform backend

- [ ] **Step 1: Create the S3 state bucket in ap-southeast-2**

```bash
aws s3api create-bucket \
  --bucket multitenant-sso-terraform-state-apse2 \
  --region ap-southeast-2 \
  --create-bucket-configuration LocationConstraint=ap-southeast-2
```

Expected: `{ "Location": "http://multitenant-sso-terraform-state-apse2.s3.amazonaws.com/" }`

- [ ] **Step 2: Enable versioning on the bucket**

```bash
aws s3api put-bucket-versioning \
  --bucket multitenant-sso-terraform-state-apse2 \
  --versioning-configuration Status=Enabled
```

Expected: no output (success is silent)

- [ ] **Step 3: Enable server-side encryption on the bucket**

```bash
aws s3api put-bucket-encryption \
  --bucket multitenant-sso-terraform-state-apse2 \
  --server-side-encryption-configuration '{
    "Rules": [{
      "ApplyServerSideEncryptionByDefault": {
        "SSEAlgorithm": "AES256"
      }
    }]
  }'
```

Expected: no output (success is silent)

- [ ] **Step 4: Block public access on the bucket**

```bash
aws s3api put-public-access-block \
  --bucket multitenant-sso-terraform-state-apse2 \
  --public-access-block-configuration \
    "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

Expected: no output (success is silent)

- [ ] **Step 5: Create the DynamoDB lock table in ap-southeast-2**

```bash
aws dynamodb create-table \
  --table-name multitenant-sso-terraform-locks \
  --attribute-definitions AttributeName=LockID,AttributeType=S \
  --key-schema AttributeName=LockID,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST \
  --region ap-southeast-2
```

Expected: JSON output with `"TableStatus": "CREATING"`. Wait ~10 seconds then verify:

```bash
aws dynamodb describe-table \
  --table-name multitenant-sso-terraform-locks \
  --region ap-southeast-2 \
  --query 'Table.TableStatus'
```

Expected: `"ACTIVE"`

---

### Task 3: Update Terraform config for ap-southeast-2

**Files:**
- Modify: `infrastructure/terraform/variables.tf`
- Modify: `infrastructure/terraform/main.tf`

**Interfaces:**
- Consumes: S3 bucket `multitenant-sso-terraform-state-apse2` and DynamoDB table from Task 2
- Produces: Terraform config pointing at ap-southeast-2 backend and region

- [ ] **Step 1: Update `variables.tf` default region**

In `infrastructure/terraform/variables.tf`, change line 3:

```hcl
variable "aws_region" {
  description = "AWS region"
  default     = "ap-southeast-2"
}
```

- [ ] **Step 2: Update `main.tf` backend config**

In `infrastructure/terraform/main.tf`, replace the `backend "s3"` block:

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
    bucket         = "multitenant-sso-terraform-state-apse2"
    key            = "production/terraform.tfstate"
    region         = "ap-southeast-2"
    dynamodb_table = "multitenant-sso-terraform-locks"
    encrypt        = true
  }
}

provider "aws" {
  region = var.aws_region
}
```

- [ ] **Step 3: Reinitialise Terraform with the new backend**

```bash
cd infrastructure/terraform
terraform init -reconfigure
```

Expected: `Terraform has been successfully initialized!` with a message about the new backend.

- [ ] **Step 4: Validate the config**

```bash
terraform validate
```

Expected: `Success! The configuration is valid.`

- [ ] **Step 5: Commit the config changes**

```bash
git add infrastructure/terraform/variables.tf infrastructure/terraform/main.tf
git commit -m "feat(infra): migrate Terraform backend and region to ap-southeast-2"
```

---

### Task 4: Apply infrastructure in ap-southeast-2

**Files:**
- No file changes — runs `terraform apply` against updated config from Task 3

**Interfaces:**
- Produces: all AWS resources live in ap-southeast-2; `terraform output` values available for Tasks 5–7

- [ ] **Step 1: Preview the apply**

```bash
cd infrastructure/terraform
terraform plan
```

Expected: plan showing N resources to add, 0 to change, 0 to destroy. The resource list should include VPC, subnets, security groups, EFS, ECR repos, ECS cluster, ECS task definitions (app + staging), ECS services (app + staging), ALB, ACM certs, Secrets Manager secrets, IAM roles, OIDC provider.

- [ ] **Step 2: Apply**

```bash
terraform apply -auto-approve
```

This takes 10–20 minutes. The ACM certificate validation steps have a 60-minute timeout but will complete as soon as DNS records are added (Task 5). If the apply pauses at ACM validation, proceed to Task 5 in a new terminal — Terraform will continue once DNS propagates.

Expected final line: `Apply complete! Resources: N added, 0 changed, 0 destroyed.`

- [ ] **Step 3: Capture outputs**

```bash
terraform output
```

Save all values — you need them for Tasks 5, 6, and 7. Key outputs:

```
alb_dns_name                 = "<alb>.ap-southeast-2.elb.amazonaws.com"
ecr_app_url                  = "<account>.dkr.ecr.ap-southeast-2.amazonaws.com/multitenant-sso-app"
ecr_nginx_url                = "<account>.dkr.ecr.ap-southeast-2.amazonaws.com/multitenant-sso-nginx"
github_actions_role_arn      = "arn:aws:iam::<account>:role/multitenant-sso-github-actions"
secrets_manager_arn          = "arn:aws:secretsmanager:ap-southeast-2:..."
staging_secrets_manager_arn  = "arn:aws:secretsmanager:ap-southeast-2:..."
acm_validation_cname         = { ... }
staging_acm_validation_cname = { ... }
ecs_cluster_name             = "multitenant-sso"
ecs_service_name             = "multitenant-sso"
```

---

### Task 5: Populate Secrets Manager secrets

**Files:**
- No file changes — AWS CLI commands against the new Secrets Manager secrets

**Interfaces:**
- Consumes: `secrets_manager_arn` and `staging_secrets_manager_arn` from Task 4 outputs
- Produces: both secrets populated; ECS tasks can start

- [ ] **Step 1: Get the existing APP_KEY from your local .env or generate a new one**

If you have a local `.env` with `APP_KEY=base64:...`, use that value. Otherwise generate:

```bash
php artisan key:generate --show
```

- [ ] **Step 2: Choose a db_password for production**

Pick a strong random password (e.g. `openssl rand -base64 32`). Note it — you cannot retrieve it from Secrets Manager later without the ARN.

- [ ] **Step 3: Populate production secret**

Replace `<app_key>` and `<db_password>` with real values:

```bash
aws secretsmanager put-secret-value \
  --secret-id "multitenant-sso/app" \
  --secret-string '{"app_key":"<app_key>","db_password":"<db_password>"}' \
  --region ap-southeast-2
```

Expected: JSON with `"VersionId"` field.

- [ ] **Step 4: Populate staging secret**

Staging can use the same app_key or a different one. Use a different db_password from production:

```bash
aws secretsmanager put-secret-value \
  --secret-id "multitenant-sso/staging" \
  --secret-string '{"app_key":"<staging_app_key>","db_password":"<staging_db_password>"}' \
  --region ap-southeast-2
```

Expected: JSON with `"VersionId"` field.

---

### Task 6: Add DNS records in A2Hosting

**Files:**
- No file changes — DNS changes in A2Hosting control panel (manual)

**Interfaces:**
- Consumes: `acm_validation_cname`, `staging_acm_validation_cname`, `alb_dns_name` from Task 4 outputs
- Produces: ACM certs validated; domains resolve to new ALB

- [ ] **Step 1: Get ACM validation CNAME values**

```bash
cd infrastructure/terraform
terraform output acm_validation_cname
terraform output staging_acm_validation_cname
```

Each output has `resource_record_name`, `resource_record_value`, and `resource_record_type = CNAME`.

- [ ] **Step 2: Add ACM validation CNAMEs in A2Hosting**

In A2Hosting DNS manager, add two CNAME records:
- Name: `<resource_record_name for production cert>` → Value: `<resource_record_value>`
- Name: `<resource_record_name for staging cert>` → Value: `<resource_record_value>`

Strip the trailing dot from names/values if A2Hosting doesn't accept them.

- [ ] **Step 3: Update ALB CNAME records**

In A2Hosting DNS manager, update or add CNAME records:
- `multi-tenancy.ronmartdanieljavier.com` → `<alb_dns_name from terraform output>`
- `staging.multi-tenancy.ronmartdanieljavier.com` → `<alb_dns_name from terraform output>`

Both domains point to the same ALB — routing is handled by ALB host-header rules.

- [ ] **Step 4: Wait for ACM validation**

ACM validates within minutes of DNS propagation. Check status:

```bash
aws acm list-certificates --region ap-southeast-2 \
  --query 'CertificateSummaryList[*].[DomainName,Status]' \
  --output table
```

Expected: both certs show `ISSUED` status. If Terraform is still waiting on ACM validation (from Task 4 Step 2), it will complete automatically once this step is done.

---

### Task 7: Update GitHub Actions secrets

**Files:**
- No file changes — GitHub repository secrets updated via `gh` CLI or GitHub web UI

**Interfaces:**
- Consumes: all `terraform output` values from Task 4
- Produces: CI/CD can authenticate to AWS and push to ECR in ap-southeast-2

- [ ] **Step 1: Update AWS_ROLE_ARN**

```bash
gh secret set AWS_ROLE_ARN \
  --body "$(cd infrastructure/terraform && terraform output -raw github_actions_role_arn)" \
  --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

- [ ] **Step 2: Update ECR_APP_REPOSITORY**

```bash
gh secret set ECR_APP_REPOSITORY \
  --body "$(cd infrastructure/terraform && terraform output -raw ecr_app_url)" \
  --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

- [ ] **Step 3: Update ECR_NGINX_REPOSITORY**

```bash
gh secret set ECR_NGINX_REPOSITORY \
  --body "$(cd infrastructure/terraform && terraform output -raw ecr_nginx_url)" \
  --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

- [ ] **Step 4: Update ECS_CLUSTER**

```bash
gh secret set ECS_CLUSTER \
  --body "$(cd infrastructure/terraform && terraform output -raw ecs_cluster_name)" \
  --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

- [ ] **Step 5: Update ECS_SERVICE**

```bash
gh secret set ECS_SERVICE \
  --body "$(cd infrastructure/terraform && terraform output -raw ecs_service_name)" \
  --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

- [ ] **Step 6: Verify secrets are set**

```bash
gh secret list --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

Expected: all five secrets show a recent `Updated` timestamp.

---

### Task 8: Update CI/CD workflow to ap-southeast-2

**Files:**
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: updated GitHub secrets from Task 7
- Produces: CI/CD workflow deploys to ap-southeast-2 on push

- [ ] **Step 1: Replace all us-east-1 references in the workflow**

In `.github/workflows/ci.yml`, make these changes:

**deploy-staging job** — `Configure AWS credentials via OIDC` step, change:
```yaml
          aws-region: us-east-1
```
to:
```yaml
          aws-region: ap-southeast-2
```

**deploy-staging job** — `Deploy to staging ECS service` step, change:
```yaml
            --region us-east-1
```
to:
```yaml
            --region ap-southeast-2
```

**deploy-staging job** — `Wait for staging deployment to stabilize` step, change:
```yaml
            --region us-east-1
```
to:
```yaml
            --region ap-southeast-2
```

**deploy-production job** — `Configure AWS credentials via OIDC` step, change:
```yaml
          aws-region: us-east-1
```
to:
```yaml
          aws-region: ap-southeast-2
```

**deploy-production job** — `Deploy to production ECS service` step, change:
```yaml
            --region us-east-1
```
to:
```yaml
            --region ap-southeast-2
```

**deploy-production job** — `Wait for production deployment to stabilize` step, change:
```yaml
            --region us-east-1
```
to:
```yaml
            --region ap-southeast-2
```

- [ ] **Step 2: Verify no us-east-1 references remain**

```bash
grep -n "us-east-1" .github/workflows/ci.yml
```

Expected: no output.

- [ ] **Step 3: Commit the workflow change**

```bash
git add .github/workflows/ci.yml
git commit -m "feat(ci): migrate CI/CD region to ap-southeast-2"
```

---

### Task 9: Verify staging deployment

**Files:**
- No file changes — push triggers CI/CD

**Interfaces:**
- Consumes: all prior tasks complete; secrets set; DNS propagated; ACM certs issued
- Produces: staging environment running in ap-southeast-2

- [ ] **Step 1: Push to main to trigger staging deploy**

```bash
git push origin main
```

- [ ] **Step 2: Monitor the GitHub Actions workflow**

```bash
gh run watch --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

Or open: https://github.com/ronmartdanieljavier/laravel-multitenant-sso-boilerplate/actions

Watch the `deploy-staging` job complete successfully.

- [ ] **Step 3: Verify staging health endpoint**

```bash
curl -I https://staging.multi-tenancy.ronmartdanieljavier.com/health
```

Expected: `HTTP/2 200`

If you get a timeout, the ECS task may still be starting (allow 2–3 minutes after deploy stabilises).

---

### Task 10: Verify production deployment

**Files:**
- No file changes — push to production branch triggers CI/CD

**Interfaces:**
- Consumes: staging verified in Task 9
- Produces: production environment running in ap-southeast-2

- [ ] **Step 1: Push to production branch**

```bash
git push origin main:production
```

- [ ] **Step 2: Monitor the GitHub Actions workflow**

```bash
gh run watch --repo ronmartdanieljavier/laravel-multitenant-sso-boilerplate
```

Watch the `deploy-production` job complete successfully.

- [ ] **Step 3: Verify production health endpoint**

```bash
curl -I https://multi-tenancy.ronmartdanieljavier.com/health
```

Expected: `HTTP/2 200`

- [ ] **Step 4: Clean up old us-east-1 state bucket (optional)**

Once production is confirmed healthy, delete the old state bucket:

```bash
# Empty the bucket first (versioned, so must delete all versions)
aws s3api delete-objects \
  --bucket multitenant-sso-terraform-state \
  --delete "$(aws s3api list-object-versions \
    --bucket multitenant-sso-terraform-state \
    --query '{Objects: Versions[].{Key:Key,VersionId:VersionId}}' \
    --output json)" \
  --region us-east-1

aws s3 rb s3://multitenant-sso-terraform-state --force --region us-east-1
```

Also delete the old DynamoDB lock table in us-east-1 if it exists:

```bash
aws dynamodb delete-table \
  --table-name multitenant-sso-terraform-locks \
  --region us-east-1
```
