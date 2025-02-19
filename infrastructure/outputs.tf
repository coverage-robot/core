output "ingest_bucket" {
  value     = module.bucket.ingest_bucket
  sensitive = true
}

output "output_bucket" {
  value     = module.bucket.output_bucket
  sensitive = true
}

output "object_reference_bucket" {
  value     = module.bucket.object_reference_bucket
  sensitive = true
}

output "publish_queue" {
  value     = module.queue.publish_queue
  sensitive = true
}

output "webhooks_queue" {
  value     = module.queue.webhooks_queue
  sensitive = true
}

output "cloudfront_distribution" {
  value     = length(module.warehouse) > 0 ? module.routing.cloudwatch_distribution : null
  sensitive = true
}

output "api_gateway" {
  value     = length(module.warehouse) > 0 ? module.routing.api_gateway : null
  sensitive = true
}

output "api_gateway_default_stage" {
  value     = length(module.warehouse) > 0 ? module.routing.api_gateway_default_stage : null
  sensitive = true
}

output "coverage_event_bus" {
  value     = module.events.coverage_event_bus
  sensitive = true
}

output "configuration_table" {
  value     = module.configuration.configuration_table
  sensitive = true
}

output "project_pool" {
  value     = module.authentication.project_pool
  sensitive = true
}

output "environment_dataset" {
  value     = length(module.warehouse) > 0 ? module.warehouse.environment_dataset : null
  sensitive = true
}

output "line_coverage_table" {
  value     = length(module.warehouse) > 0 ? module.warehouse.line_coverage_table : null
  sensitive = true
}

output "upload_table" {
  value     = length(module.warehouse) > 0 ? module.warehouse.upload_table : null
  sensitive = true
}
