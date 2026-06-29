resource "aws_secretsmanager_secret" "app" {
  name                    = "${var.app_name}/app"
  recovery_window_in_days = 0
  tags                    = { Name = var.app_name }
}

resource "aws_secretsmanager_secret" "staging" {
  name                    = "${var.app_name}/staging"
  recovery_window_in_days = 0
  tags                    = { Name = "${var.app_name}-staging" }
}
