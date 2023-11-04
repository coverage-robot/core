resource "aws_dynamodb_table" "event_table" {
  name                        = format("coverage-orchestrator-event-store-%s", var.environment)
  billing_mode                = "PROVISIONED"
  read_capacity               = 5
  write_capacity              = 5
  hash_key                    = "identifier"
  range_key                   = "version"
  deletion_protection_enabled = true

  attribute {
    name = "identifier"
    type = "S"
  }

  attribute {
    name = "version"
    type = "N"
  }

  ttl {
    attribute_name = "expiry"
    enabled        = true
  }

  lifecycle {
    ignore_changes = [
      write_capacity,
      read_capacity
    ]
  }
}