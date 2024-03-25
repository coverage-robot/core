resource "aws_cognito_user_pool" "project_pool" {
  name                = format("coverage-projects-%s", var.environment)
  alias_attributes    = ["preferred_username"]
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
    allow_admin_create_user_only = true
  }

  password_policy {
    minimum_length                   = 25
    temporary_password_validity_days = 1
    require_numbers                  = true
    require_uppercase                = true
    require_lowercase                = true
    require_symbols                  = true
  }

  schema {
    attribute_data_type = "String"
    mutable             = true
    name                = "preferred_username"
    required            = true

    string_attribute_constraints {
      min_length = 1
    }
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
