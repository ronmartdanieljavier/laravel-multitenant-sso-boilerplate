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

variable "staging_domain" {
  description = "Full subdomain for the staging environment"
  default     = "staging.multi-tenancy.ronmartdanieljavier.com"
}
