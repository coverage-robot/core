output "ingest_bucket" {
  value = module.bucket.ingest_bucket
}

output "output_bucket" {
  value = module.bucket.output_bucket
}

output "publish_queue" {
  value = module.queue.publish_queue
}

output "webhooks_queue" {
  value = module.queue.webhooks_queue
}

output "cloudfront_distribution" {
  value = length(module.warehouse) > 0 ? module.routing.cloudwatch_distribution : null
}

output "api_gateway" {
  value = length(module.warehouse) > 0 ? module.routing.api_gateway : null
}

output "api_gateway_default_stage" {
  value = length(module.warehouse) > 0 ? module.routing.api_gateway_default_stage : null
}

output "coverage_event_bus" {
  value = module.events.coverage_event_bus
}

output "configuration_table" {
  value = module.configuration.configuration_table
}

output "project_pool" {
  value = module.authentication.project_pool
}

output "environment_dataset" {
  value = length(module.warehouse) > 0 ? module.warehouse.environment_dataset : null
}

output "line_coverage_table" {
  value = length(module.warehouse) > 0 ? module.warehouse.line_coverage_table : null
}

output "upload_table" {
  value = length(module.warehouse) > 0 ? module.warehouse.upload_table : null
}