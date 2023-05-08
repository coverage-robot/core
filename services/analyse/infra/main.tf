data "archive_file" "deployment" {
    type        = "zip"
    source_dir  = "${path.module}/../"
    output_path = "${path.module}/deployment.zip"
    excludes    = [
        "composer.lock",
        "README.md",
        "tests",
        "infra",
        ".serverless"
    ]
}

resource "aws_lambda_function" "analyse" {
    filename         = "${path.module}/deployment.zip"
    source_code_hash = data.archive_file.deployment.output_base64sha256
    function_name    = format("coverage-analyse-%s", var.environment)
    role             = var.lambda_role
    runtime          = "provided.al2"
    handler          = "App\\Handler\\AnalyseHandler"
    architectures    = ["arm64"]
    layers           = [
        format(
            "arn:aws:lambda:%s:534081306603:layer:arm-php-82:%s",
            var.region,
            var.bref_layer_version
        )
    ]
}