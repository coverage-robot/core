locals {
  bref_layers = jsondecode(file("${path.module}/../../vendor/bref/bref/layers.json"))
}

data "aws_caller_identity" "current" {}

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

resource "aws_iam_role" "publish_role" {
  name = "publish-role"

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

resource "aws_iam_policy" "publish_policy" {
  name = "publish-policy"
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
          data.terraform_remote_state.core.outputs.publish_queue.arn
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "dynamodb:Query"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.configuration_table.arn
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "s3:PutObject",
          "s3:GetObject",
        ]
        Resource = [
          "${data.terraform_remote_state.core.outputs.object_reference_bucket.arn}/*"
        ]
      }

    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.publish_role.name
  policy_arn = aws_iam_policy.publish_policy.arn
}

resource "aws_lambda_function" "service" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-publish-%s", var.environment)
  role             = aws_iam_role.publish_role.arn
  runtime          = "provided.al2"
  handler          = "App\\Handler\\EventHandler"
  timeout          = 30
  architectures    = ["arm64"]
  layers = [
    format(
      "arn:aws:lambda:%s:534081306603:layer:arm-${var.php_version}:%s",
      var.region,
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
      "AWS_ACCOUNT_ID"                     = data.aws_caller_identity.current.account_id
      "OBJECT_REFERENCE_STORE_BUCKET_NAME" = data.terraform_remote_state.core.outputs.object_reference_bucket.bucket,
    }
  }
}

resource "aws_lambda_function_event_invoke_config" "service_invoke_config" {
  function_name = aws_lambda_function.service.function_name

  maximum_retry_attempts = 0
}

resource "aws_lambda_event_source_mapping" "event_source_mapping" {
  event_source_arn = data.terraform_remote_state.core.outputs.publish_queue.arn
  enabled          = true
  function_name    = aws_lambda_function.service.arn
}

