resource "aws_secretsmanager_secret" "app" {
  name                    = "${var.app_name}/app"
  recovery_window_in_days = 0
  tags                    = { Name = var.app_name }
}
