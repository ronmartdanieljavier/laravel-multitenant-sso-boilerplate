# Design: Migrate AWS Infrastructure to ap-southeast-2

**Date:** 2026-06-30
**Scope:** Full region migration — us-east-1 → ap-southeast-2 (Sydney), both staging and production environments

---

## Goal

Destroy all AWS infrastructure in us-east-1 and recreate it identically in ap-southeast-2. No architectural changes — this is a pure region migration.

---

## What Changes

### Terraform (`infrastructure/terraform/`)

| File | Change |
|------|--------|
| `variables.tf` | `aws_region` default: `us-east-1` → `ap-southeast-2` |
| `main.tf` | Backend `region`: `us-east-1` → `ap-southeast-2`; `bucket`: `multitenant-sso-terraform-state` → `multitenant-sso-terraform-state-apse2` |

All other `.tf` files use `var.aws_region` and require no changes.

### CI/CD (`.github/workflows/ci.yml`)

Four hardcoded `us-east-1` references:

- `deploy-staging` → `configure-aws-credentials`: `aws-region: us-east-1`
- `deploy-staging` → `aws ecs update-service --region us-east-1`
- `deploy-staging` → `aws ecs wait services-stable --region us-east-1`
- `deploy-production` → `configure-aws-credentials`: `aws-region: us-east-1`
- `deploy-production` → `aws ecs update-service --region us-east-1`
- `deploy-production` → `aws ecs wait services-stable --region us-east-1`

All replaced with `ap-southeast-2`.

### GitHub Actions Secrets (manual update after apply)

| Secret | Source |
|--------|--------|
| `AWS_ROLE_ARN` | `terraform output github_actions_role_arn` |
| `ECR_APP_REPOSITORY` | `terraform output ecr_app_url` |
| `ECR_NGINX_REPOSITORY` | `terraform output ecr_nginx_url` |
| `ECS_CLUSTER` | `terraform output ecs_cluster_name` |
| `ECS_SERVICE` | `terraform output ecs_service_name` |

---

## Migration Sequence

### Phase 1 — Destroy us-east-1

1. Run `terraform destroy` against the existing state (`multitenant-sso-terraform-state` bucket, `us-east-1`)
2. Confirm all resources are destroyed

### Phase 2 — Bootstrap ap-southeast-2 state backend

3. Create S3 bucket `multitenant-sso-terraform-state-apse2` in ap-southeast-2 (versioning + encryption enabled)
4. Create DynamoDB table `multitenant-sso-terraform-locks` in ap-southeast-2 (partition key: `LockID`, type: String)

### Phase 3 — Update Terraform config and apply

5. Update `variables.tf`: `aws_region` default → `ap-southeast-2`
6. Update `main.tf` backend: `region` + `bucket` for ap-southeast-2
7. Run `terraform init` (new backend, no state to migrate)
8. Run `terraform apply` — provisions all resources in ap-southeast-2

### Phase 4 — Post-apply manual steps

9. Populate Secrets Manager secrets in ap-southeast-2:
   - `multitenant-sso/app`: `app_key`, `db_password`
   - `multitenant-sso/staging`: `app_key`, `db_password`
10. Add DNS records in A2Hosting:
    - ACM validation CNAMEs (from `terraform output acm_validation_cname` and `staging_acm_validation_cname`)
    - ALB CNAME for `multi-tenancy.ronmartdanieljavier.com` → `terraform output alb_dns_name`
    - ALB CNAME for `staging.multi-tenancy.ronmartdanieljavier.com` → same ALB DNS name
11. Update GitHub Actions secrets with new values from `terraform output`

### Phase 5 — Update CI/CD and verify

12. Replace all `us-east-1` region strings in `.github/workflows/ci.yml` with `ap-southeast-2`
13. Commit and push to `main` → triggers staging deploy
14. Verify staging health endpoint responds 200
15. Push to `production` branch → triggers production deploy
16. Verify production health endpoint responds 200

---

## State Bucket Notes

- Old bucket `multitenant-sso-terraform-state` (us-east-1) can be manually deleted after confirming the new infrastructure is healthy
- New bucket name `multitenant-sso-terraform-state-apse2` avoids naming conflicts (S3 names are globally unique)
- DynamoDB table name `multitenant-sso-terraform-locks` is reused — safe because it's a different region

---

## Risks / Mitigations

| Risk | Mitigation |
|------|------------|
| ACM cert validation takes up to 60m | DNS records must be added before `terraform apply` finishes waiting, or re-run after adding them |
| Secrets not populated before ECS deploy | Populate secrets immediately after apply, before pushing to main/production |
| Old ECR images don't exist in new region | CI/CD will build fresh images on first deploy — expected |
| GitHub secrets not updated before push | Update secrets before any push that triggers deploy |
