output "environment_dataset" {
  value = google_bigquery_dataset.environment_dataset
}

output "line_coverage_table" {
  value = google_bigquery_table.line_coverage
}

output "upload_table" {
  value = google_bigquery_table.upload
}