data "archive_file" "deployment" {
    type        = "zip"
    source_dir  = "${path.module}/../"
    output_path = "${path.module}/deployment.zip"
    excludes    = [
        "composer.lock",
        "README.md",
        "tests",
        "infrastructure"
    ]
}

resource "aws_lambda_function" "ingest" {
    filename         = "${path.module}/deployment.zip"
    source_code_hash = data.archive_file.deployment.output_base64sha256
    function_name    = format("coverage-ingest-%s", var.environment)
    role             = var.lambda_role
    runtime          = "provided.al2"
    handler          = "App\\Handler\\IngestHandler"
    architectures    = ["arm64"]
    layers           = [
        format(
            "arn:aws:lambda:%s:534081306603:layer:arm-php-82:%s",
            var.region,
            var.bref_layer_version
        )
    ]
}

resource "aws_lambda_permission" "allow_bucket" {
    statement_id  = "AllowExecutionFromS3Bucket"
    action        = "lambda:InvokeFunction"
    function_name = aws_lambda_function.ingest.arn
    principal     = "s3.amazonaws.com"
    source_arn    = var.ingest_bucket.arn
}

resource "aws_s3_bucket_notification" "ingest_trigger" {
    bucket = var.ingest_bucket.id

    lambda_function {
        lambda_function_arn = aws_lambda_function.ingest.arn
        events              = ["s3:ObjectCreated:*"]
    }

    depends_on = [
        aws_lambda_permission.allow_bucket
    ]
}