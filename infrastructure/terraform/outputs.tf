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
