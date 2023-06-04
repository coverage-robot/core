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

resource "aws_s3_bucket_versioning" "versioning_example" {
  bucket = aws_s3_bucket.coverage_ingest.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "example" {
  bucket = aws_s3_bucket.coverage_ingest.id

  rule {
    id = "delete-ingested-coverage-files"

    noncurrent_version_expiration {
      noncurrent_days = 1
    }

    status = "Enabled"
  }
}