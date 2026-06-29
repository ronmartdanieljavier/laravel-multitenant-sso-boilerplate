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
