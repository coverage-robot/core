output "ingest_bucket" {
  value = module.bucket.ingest_bucket
}

output "output_bucket" {
  value = module.bucket.output_bucket
}

output "analysis_queue" {
  value = module.queue.analysis_queue
}

output "environment_dataset" {
  value = module.warehouse.environment_dataset
}

output "line_coverage_table" {
  value = module.warehouse.line_coverage_table
}