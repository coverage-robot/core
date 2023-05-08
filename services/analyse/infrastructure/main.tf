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

resource "aws_lambda_function" "analyse" {
    filename         = "${path.module}/deployment.zip"
    source_code_hash = data.archive_file.deployment.output_base64sha256

    function_name = format("coverage-analyse-%s", var.environment)
    role          = var.lambda_role.arn
    runtime       = "provided.al2"
    handler       = "App\\Handler\\AnalyseHandler"
    architectures = ["arm64"]

    layers = [
        format(
            "arn:aws:lambda:%s:534081306603:layer:arm-php-82:%s",
            var.region,
            var.bref_layer_version
        )
    ]
}

resource "aws_lambda_event_source_mapping" "analysis_trigger" {
    function_name                      = aws_lambda_function.analyse.arn
    event_source_arn                   = var.analysis_queue.arn
    batch_size                         = 1
    maximum_batching_window_in_seconds = 60
    maximum_retry_attempts             = 0
    function_response_types            = "ReportBatchItemFailures"
}