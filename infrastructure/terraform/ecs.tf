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
  app_image           = "${aws_ecr_repository.app.repository_url}:latest"
  nginx_image         = "${aws_ecr_repository.nginx.repository_url}:latest"
  staging_app_image   = "${aws_ecr_repository.app.repository_url}:staging-latest"
  staging_nginx_image = "${aws_ecr_repository.nginx.repository_url}:staging-latest"
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
        iam             = "ENABLED"
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
        iam             = "ENABLED"
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
        iam             = "ENABLED"
      }
    }
  }

  container_definitions = jsonencode([
    {
      name      = "postgres"
      image     = "postgres:17-alpine"
      essential = true
      user      = "70:70"
      environment = [
        { name = "POSTGRES_DB", value = var.db_name },
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
      command   = ["redis-server", "--save", "60", "1"]
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
        { containerName = "redis", condition = "HEALTHY" }
      ]
      secrets = [
        { name = "APP_KEY", valueFrom = "${aws_secretsmanager_secret.app.arn}:app_key::" },
        { name = "DB_PASSWORD", valueFrom = "${aws_secretsmanager_secret.app.arn}:db_password::" }
      ]
      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "APP_DEBUG", value = "false" },
        { name = "APP_URL", value = "https://${var.domain}" },
        { name = "DB_CONNECTION", value = "pgsql" },
        { name = "DB_HOST", value = "127.0.0.1" },
        { name = "DB_PORT", value = "5432" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_username },
        { name = "REDIS_HOST", value = "127.0.0.1" },
        { name = "REDIS_PORT", value = "6379" },
        { name = "CACHE_STORE", value = "redis" },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "LOG_CHANNEL", value = "stderr" },
        { name = "LARAVEL_PDF_DRIVER", value = "dompdf" }
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
        { name = "APP_KEY", valueFrom = "${aws_secretsmanager_secret.app.arn}:app_key::" },
        { name = "DB_PASSWORD", valueFrom = "${aws_secretsmanager_secret.app.arn}:db_password::" }
      ]
      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "APP_DEBUG", value = "false" },
        { name = "APP_URL", value = "https://${var.domain}" },
        { name = "DB_CONNECTION", value = "pgsql" },
        { name = "DB_HOST", value = "127.0.0.1" },
        { name = "DB_PORT", value = "5432" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_username },
        { name = "REDIS_HOST", value = "127.0.0.1" },
        { name = "REDIS_PORT", value = "6379" },
        { name = "CACHE_STORE", value = "redis" },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "LOG_CHANNEL", value = "stderr" },
        { name = "LARAVEL_PDF_DRIVER", value = "dompdf" }
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

resource "aws_ecs_task_definition" "staging" {
  family                   = "${var.app_name}-staging"
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
        access_point_id = aws_efs_access_point.staging_postgres.id
        iam             = "ENABLED"
      }
    }
  }

  volume {
    name = "redis-data"
    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.main.id
      transit_encryption = "ENABLED"
      authorization_config {
        access_point_id = aws_efs_access_point.staging_redis.id
        iam             = "ENABLED"
      }
    }
  }

  volume {
    name = "app-storage"
    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.main.id
      transit_encryption = "ENABLED"
      authorization_config {
        access_point_id = aws_efs_access_point.staging_storage.id
        iam             = "ENABLED"
      }
    }
  }

  container_definitions = jsonencode([
    {
      name      = "postgres"
      image     = "postgres:17-alpine"
      essential = true
      user      = "70:70"
      environment = [
        { name = "POSTGRES_DB", value = var.db_name },
        { name = "POSTGRES_USER", value = var.db_username }
      ]
      secrets = [
        { name = "POSTGRES_PASSWORD", valueFrom = "${aws_secretsmanager_secret.staging.arn}:db_password::" }
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
          "awslogs-stream-prefix" = "staging-postgres"
        }
      }
    },
    {
      name      = "redis"
      image     = "redis:7-alpine"
      essential = true
      command   = ["redis-server", "--save", "60", "1"]
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
          "awslogs-stream-prefix" = "staging-redis"
        }
      }
    },
    {
      name      = "php"
      image     = local.staging_app_image
      essential = true
      dependsOn = [
        { containerName = "postgres", condition = "HEALTHY" },
        { containerName = "redis", condition = "HEALTHY" }
      ]
      secrets = [
        { name = "APP_KEY", valueFrom = "${aws_secretsmanager_secret.staging.arn}:app_key::" },
        { name = "DB_PASSWORD", valueFrom = "${aws_secretsmanager_secret.staging.arn}:db_password::" }
      ]
      environment = [
        { name = "APP_ENV", value = "staging" },
        { name = "APP_DEBUG", value = "true" },
        { name = "APP_URL", value = "https://${var.staging_domain}" },
        { name = "DB_CONNECTION", value = "pgsql" },
        { name = "DB_HOST", value = "127.0.0.1" },
        { name = "DB_PORT", value = "5432" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_username },
        { name = "REDIS_HOST", value = "127.0.0.1" },
        { name = "REDIS_PORT", value = "6379" },
        { name = "CACHE_STORE", value = "redis" },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "LOG_CHANNEL", value = "stderr" },
        { name = "LARAVEL_PDF_DRIVER", value = "dompdf" }
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
          "awslogs-stream-prefix" = "staging-php"
        }
      }
    },
    {
      name      = "nginx"
      image     = local.staging_nginx_image
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
          "awslogs-stream-prefix" = "staging-nginx"
        }
      }
    },
    {
      name      = "horizon"
      image     = local.staging_app_image
      essential = false
      command   = ["php", "artisan", "horizon"]
      dependsOn = [
        { containerName = "php", condition = "START" }
      ]
      secrets = [
        { name = "APP_KEY", valueFrom = "${aws_secretsmanager_secret.staging.arn}:app_key::" },
        { name = "DB_PASSWORD", valueFrom = "${aws_secretsmanager_secret.staging.arn}:db_password::" }
      ]
      environment = [
        { name = "APP_ENV", value = "staging" },
        { name = "APP_DEBUG", value = "true" },
        { name = "APP_URL", value = "https://${var.staging_domain}" },
        { name = "DB_CONNECTION", value = "pgsql" },
        { name = "DB_HOST", value = "127.0.0.1" },
        { name = "DB_PORT", value = "5432" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_username },
        { name = "REDIS_HOST", value = "127.0.0.1" },
        { name = "REDIS_PORT", value = "6379" },
        { name = "CACHE_STORE", value = "redis" },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "LOG_CHANNEL", value = "stderr" },
        { name = "LARAVEL_PDF_DRIVER", value = "dompdf" }
      ]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/${var.app_name}"
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "staging-horizon"
        }
      }
    }
  ])
}

resource "aws_ecs_service" "staging" {
  name            = "${var.app_name}-staging"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.staging.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = [aws_subnet.public_a.id, aws_subnet.public_b.id]
    security_groups  = [aws_security_group.app.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.staging.arn
    container_name   = "nginx"
    container_port   = 80
  }

  lifecycle {
    ignore_changes = [task_definition]
  }

  depends_on = [aws_lb_listener_rule.staging]
}
