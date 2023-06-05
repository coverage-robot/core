resource "aws_s3_bucket" "coverage_ingest" {
  bucket = format("coverage-ingest-%s", var.environment)

  tags = {
    environment = var.environment
  }
}

resource "aws_s3_bucket_versioning" "ingest_versioning" {
  bucket = aws_s3_bucket.coverage_ingest.id

  versioning_configuration {
    status = "Enabled"
  }

  depends_on = [
    aws_s3_bucket.coverage_ingest
  ]
}

resource "aws_s3_bucket_lifecycle_configuration" "ingest_lifecycle" {
  bucket = aws_s3_bucket.coverage_ingest.id

  rule {
    id = "delete-ingested-coverage-files"

    expiration {
      # Delete the current version of objects after 7 days. These are the files
      # which failed to be ingested correctly.
      days = 7
    }

    noncurrent_version_expiration {
      # Delete any objects holding a delete marker (or that are old version) after 1 day. These
      # are the files which were successfully ingested.
      noncurrent_days = 1
    }

    abort_incomplete_multipart_upload {
      days_after_initiation = 1
    }

    status = "Enabled"
  }

  depends_on = [
    aws_s3_bucket.coverage_ingest
  ]
}

resource "aws_s3_bucket" "coverage_output" {
  bucket = format("coverage-output-%s", var.environment)

  tags = {
    environment = var.environment
  }
}