output "ingest_bucket" {
  value = aws_s3_bucket.coverage_ingest
}

output "output_bucket" {
  value = aws_s3_bucket.coverage_output
}

output "object_reference_bucket" {
  value = aws_s3_bucket.service_object_references
}
