resource "aws_dynamodb_table" "configuration_table" {
  name                        = format("coverage-configuration-%s", var.environment)
  billing_mode                = "PROVISIONED"
  read_capacity               = 3
  write_capacity              = 3
  hash_key                    = "repositoryIdentifier"
  range_key                   = "setting"
  deletion_protection_enabled = true

  attribute {
    name = "repositoryIdentifier"
    type = "S"
  }

  attribute {
    name = "settingKey"
    type = "S"
  }

  lifecycle {
    ignore_changes = [
      write_capacity,
      read_capacity
    ]
  }
}