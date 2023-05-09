locals {
    bref_layers = jsondecode(file("${path.module}/../vendor/bref/bref/layers.json"))
    php_version = "arm-php-82"
}

resource "aws_iam_role" "ingest_policy" {
    name               = "ingest-service-policy"
    assume_role_policy = jsonencode({
        Version   = "2012-10-17"
        Statement = [
            {
                Action    = "sts:AssumeRole"
                Effect    = "Allow"
                Principal = {
                    Service = "lambda.amazonaws.com"
                }
            }
        ]
    })
}

resource "aws_iam_policy" "ingest_service_policy" {
    name   = "ingest-service-policy"
    path   = "/"
    policy = jsonencode({
        Version   = "2012-10-17"
        Statement = concat(
            var.policy_statements,
            [
                {
                    Effect = "Allow"
                    Action = [
                        "s3:GetObject"
                    ]
                    Resource = [
                        "${var.ingest_bucket.arn}/*"
                    ]
                },
                {
                    Effect = "Allow"
                    Action = [
                        "s3:PutObject"
                    ]
                    Resource = [
                        "${var.output_bucket.arn}/*"
                    ]
                },
                {
                    Effect = "Allow"
                    Action = [
                        "sqs:*"
                    ]
                    Resource = [
                        var.analysis_queue.arn
                    ]
                }
            ]
        )
    })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
    role       = aws_iam_role.ingest_policy.name
    policy_arn = aws_iam_policy.ingest_service_policy.arn
}

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

resource "aws_lambda_function" "service" {
    filename         = "${path.module}/deployment.zip"
    source_code_hash = data.archive_file.deployment.output_base64sha256
    function_name    = format("coverage-ingest-%s", var.environment)
    role             = aws_iam_role.ingest_policy.arn
    runtime          = "provided.al2"
    handler          = "App\\Handler\\IngestHandler"
    architectures    = ["arm64"]
    timeout          = 28
    layers           = [
        format(
            "arn:aws:lambda:%s:534081306603:layer:${local.php_version}:%s",
            var.region,
            local.bref_layers[local.php_version][var.region]
        )
    ]

    environment {
        variables = {
            "ANALYSIS_QUEUE_DSN" = var.analysis_queue.url
        }
    }
}

resource "aws_lambda_permission" "allow_bucket" {
    statement_id  = "AllowExecutionFromS3Bucket"
    action        = "lambda:InvokeFunction"
    function_name = aws_lambda_function.service.arn
    principal     = "s3.amazonaws.com"
    source_arn    = var.ingest_bucket.arn
}

resource "aws_s3_bucket_notification" "ingest_trigger" {
    bucket = var.ingest_bucket.id

    lambda_function {
        lambda_function_arn = aws_lambda_function.service.arn
        events              = ["s3:ObjectCreated:*"]
    }

    depends_on = [
        aws_lambda_permission.allow_bucket
    ]
}