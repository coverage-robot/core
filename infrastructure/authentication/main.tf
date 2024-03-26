resource "aws_cognito_user_pool" "project_pool" {
  name                = format("coverage-projects-%s", var.environment)
  deletion_protection = "ACTIVE"

  username_configuration {
    case_sensitive = false
  }

  account_recovery_setting {
    recovery_mechanism {
      name     = "admin_only"
      priority = 1
    }
  }

  admin_create_user_config {
    # Requests to create users will only be performed by admins, but it will go through the sign up process
    # without using the admin scoped requests. This allows the clients to do a single request to commit a
    # new project to the pool (i.e. no need to perform password confirmations, etc).
    allow_admin_create_user_only = false
  }

  password_policy {
    minimum_length                   = 25
    temporary_password_validity_days = 1
    require_numbers                  = false
    require_uppercase                = false
    require_lowercase                = false
    require_symbols                  = false
  }

  schema {
    attribute_data_type      = "String"
    developer_only_attribute = false
    mutable                  = true
    name                     = "provider"
    required                 = false

    string_attribute_constraints {
      min_length = 1
    }
  }

  schema {
    attribute_data_type      = "String"
    developer_only_attribute = false
    mutable                  = true
    name                     = "owner"
    required                 = false

    string_attribute_constraints {
      min_length = 1
    }
  }

  schema {
    attribute_data_type      = "String"
    developer_only_attribute = false
    mutable                  = true
    name                     = "repository"
    required                 = false

    string_attribute_constraints {
      min_length = 1
    }
  }

  schema {
    attribute_data_type      = "String"
    developer_only_attribute = false
    mutable                  = true
    name                     = "graph_token"
    required                 = false

    string_attribute_constraints {
      min_length = 25
    }
  }

  lifecycle {
    ignore_changes = [
      password_policy,
      schema
    ]
  }
}
