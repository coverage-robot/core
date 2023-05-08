locals {
    bref_layers = jsondecode(file("${path.module}/../vendor/bref/bref/layers.json"))
    php_version = "arm-php-82"
}

resource "aws_iam_role" "analyse_role" {
    name               = "analyse-service-role"
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

resource "aws_iam_policy" "analyse_service_policy" {
    name   = "analyse-service-policy"
    path   = "/"
    policy = jsonencode({
        Version   = "2012-10-17"
        Statement = concat(
            var.policy_statements,
            [
                {
                    Effect = "Allow"
                    Action = [
                        "sqs:ReceiveMessage",
                        "sqs:DeleteMessage",
                        "sqs:GetQueueAttributes"
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
    role       = aws_iam_role.analyse_role.name
    policy_arn = aws_iam_policy.analyse_service_policy.arn
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

    function_name = format("coverage-analyse-%s", var.environment)
    role          = aws_iam_role.analyse_role.arn
    timeout       = 28
    runtime       = "provided.al2"
    handler       = "App\\Handler\\AnalyseHandler"
    architectures = ["arm64"]

    layers = [
        format(
            "arn:aws:lambda:%s:534081306603:layer:${local.php_version}:%s",
            var.region,
            local.bref_layers[local.php_version][var.region]
        )
    ]
}

resource "aws_lambda_event_source_mapping" "analysis_trigger" {
    function_name                      = aws_lambda_function.service.arn
    event_source_arn                   = var.analysis_queue.arn
    batch_size                         = 1
    maximum_batching_window_in_seconds = 60
    enabled                            = true
    function_response_types            = toset(["ReportBatchItemFailures"])
}