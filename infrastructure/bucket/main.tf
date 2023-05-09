resource "aws_s3_bucket" "coverage_ingest" {
  bucket = format("coverage-ingest-%s", var.environment)

  tags = {
    environment = var.environment
  }
}

resource "aws_s3_bucket" "coverage_output" {
  bucket = format("coverage-output-%s", var.environment)

  tags = {
    environment = var.environment
  }
}