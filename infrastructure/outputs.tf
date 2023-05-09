output "ingest_bucket" {
    value = module.bucket.ingest_bucket
}

output "output_bucket" {
    value = module.bucket.output_bucket
}

output "analysis_queue" {
    value = module.queue.analysis_queue
}