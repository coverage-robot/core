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

        noncurrent_version_expiration {
            noncurrent_days = 1
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