resource "aws_dynamodb_table" "cache_table" {
  name           = format("coverage-analyse-query-cache-%s", var.environment)
  billing_mode   = "PROVISIONED"
  read_capacity  = 5
  write_capacity = 5
  hash_key       = "cacheKey"

  attribute {
    name = "cacheKey"
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