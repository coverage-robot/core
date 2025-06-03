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

resource "aws_s3_bucket_lifecycle_configuration" "output_lifecycle" {
  bucket = aws_s3_bucket.coverage_output.id

  rule {
    id = "delete-outputted-coverage-files"

    filter {}

    expiration {
      # Delete the current version of objects after 7 days. These are the successfully
      # ingested coverage files which have an associated output model for debugging
      days = 7
    }

    noncurrent_version_expiration {
      # Delete any objects holding a delete marker (or that are old version) after 1 day. This
      # doesnt particularly happen with outputted models, as they're more for debugging purposes
      noncurrent_days = 1
    }

    abort_incomplete_multipart_upload {
      days_after_initiation = 1
    }

    status = "Enabled"
  }

  depends_on = [
    aws_s3_bucket.coverage_output
  ]
}

resource "aws_s3_bucket" "service_object_references" {
  bucket = format("coverage-object-reference-%s", var.environment)

  tags = {
    environment = var.environment
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "reference_lifecycle" {
  bucket = aws_s3_bucket.service_object_references.id

  rule {
    id = "delete-old-references"

    filter {}

    expiration {
      # Presigned requests only last 1 hour, so 1 day is more than enough time to
      # ensure that the reference is no longer needed.
      days = 1
    }

    noncurrent_version_expiration {
      noncurrent_days = 1
    }

    abort_incomplete_multipart_upload {
      days_after_initiation = 1
    }

    status = "Enabled"
  }

  depends_on = [
    aws_s3_bucket.service_object_references
  ]
}
