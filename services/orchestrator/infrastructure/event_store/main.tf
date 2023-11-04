resource "aws_dynamodb_table" "event_table" {
  name                        = format("coverage-orchestrator-event-store-%s", var.environment)
  billing_mode                = "PROVISIONED"
  read_capacity               = 5
  write_capacity              = 5
  hash_key                    = "ownerKey"
  range_key                   = "event"
  deletion_protection_enabled = true

  attribute {
    name = "ownerKey"
    type = "S"
  }

  attribute {
    name = "event"
    type = "S"
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