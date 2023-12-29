locals {
  bref_layers = jsondecode(file("${path.module}/../../vendor/bref/bref/layers.json"))
}

data "terraform_remote_state" "core" {
  backend = "s3"

  workspace = var.environment

  config = {
    bucket         = "tf-coverage-state"
    key            = "state/core/terraform.tfstate"
    region         = "eu-west-2"
    encrypt        = true
    dynamodb_table = "tf-coverage-locks"
  }
}

resource "aws_iam_role" "api_policy" {
  name = "api-webhook-handler-policy"
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_policy" "api_service_policy" {
  name = "api-webhook-handler-policy"
  path = "/"
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = ["arn:aws:logs:*:*:*"]
      },
      {
        Effect = "Allow"
        Action = [
          "xray:PutTraceSegments",
          "xray:PutTelemetryRecords",
          "xray:GetSamplingRules",
          "xray:GetSamplingTargets",
          "xray:GetSamplingStatisticSummaries"
        ]
        Resource = "*"
      },
      {
        Effect = "Allow"
        Action = [
          "sqs:ReceiveMessage",
          "sqs:DeleteMessage",
          "sqs:GetQueueAttributes"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.webhooks_queue.arn
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "events:PutEvents"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.coverage_event_bus.arn
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.api_policy.name
  policy_arn = aws_iam_policy.api_service_policy.arn
}

resource "aws_lambda_function" "webhooks" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-api-webhook-handler-%s", var.environment)
  role             = aws_iam_role.api_policy.arn
  runtime          = "provided.al2"
  handler          = "App\\Handler\\WebhookHandler"
  architectures    = ["arm64"]
  timeout          = 28
  layers = [
    format(
      "arn:aws:lambda:%s:534081306603:layer:arm-%s:%s",
      var.region,
      var.php_version,
      local.bref_layers["arm-${var.php_version}"][var.region]
    )
  ]

  tracing_config {
    # Enable AWS X-Ray for tracing of Lambda invocations. This is also paired with
    # permissions applied on the IAM policy, so theres sufficient permissions to write
    # traces
    mode = "Active"
  }

  environment {
    variables = {
      "BREF_PING_DISABLE" = "1"
    }
  }
}

resource "aws_lambda_function_event_invoke_config" "service_invoke_config" {
  function_name = aws_lambda_function.webhooks.function_name
}

resource "aws_lambda_event_source_mapping" "event_source_mapping" {
  event_source_arn = data.terraform_remote_state.core.outputs.webhooks_queue.arn
  enabled          = true
  function_name    = aws_lambda_function.webhooks.arn
}