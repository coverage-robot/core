resource "aws_dynamodb_table" "ref_metadata_table" {
  name                        = format("coverage-api-ref-metadata-%s", var.environment)
  billing_mode                = "PROVISIONED"
  read_capacity               = 3
  write_capacity              = 3
  hash_key                    = "repositoryIdentifier"
  range_key                   = "ref"
  deletion_protection_enabled = true

  attribute {
    name = "repositoryIdentifier"
    type = "S"
  }

  attribute {
    name = "ref"
    type = "S"
  }

  lifecycle {
    ignore_changes = [
      write_capacity,
      read_capacity
    ]
  }
}