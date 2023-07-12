output "ingest_bucket" {
  value = module.bucket.ingest_bucket
}

output "output_bucket" {
  value = module.bucket.output_bucket
}

output "coverage_event_bus" {
  value = module.events.coverage_event_bus
}

output "environment_dataset" {
  value = length(module.warehouse) > 0 ? module.warehouse.environment_dataset : null
}

output "line_coverage_table" {
  value = length(module.warehouse) > 0 ? module.warehouse.line_coverage_table : null
}

output "coverage_api_db" {
  value = length(module.database) > 0 ? module.database.coverage_api_db : null
}