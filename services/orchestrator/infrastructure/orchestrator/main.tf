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

resource "aws_iam_role" "orchestrator_role" {
  name = "orchestrator-role"

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

resource "aws_iam_policy" "orchestrator_policy" {
  name = "orchestrator-policy"
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
          "events:PutEvents"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.coverage_event_bus.arn
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "dynamodb:DescribeTable",
          "dynamodb:Get*",
          "dynamodb:Query",
          "dynamodb:PutItem"
        ]
        Resource = [
          var.event_store_arn,
          "${var.event_store_arn}/index/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "dynamodb:DescribeTable",
          "dynamodb:Get*",
          "dynamodb:Query",
          "dynamodb:PutItem",
          "dynamodb:DeleteItem"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.configuration_table.arn
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.orchestrator_role.name
  policy_arn = aws_iam_policy.orchestrator_policy.arn
}

resource "aws_lambda_function" "service" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-orchestrator-%s", var.environment)
  role             = aws_iam_role.orchestrator_role.arn
  runtime          = "provided.al2"
  handler          = "Packages\\Event\\Handler\\EventHandler"
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
      "EVENT_STORE"    = var.event_store_name
      "AWS_ACCOUNT_ID" = data.aws_caller_identity.current.account_id
    }
  }
}

resource "aws_lambda_function_event_invoke_config" "service_invoke_config" {
  function_name = aws_lambda_function.service.function_name

  maximum_retry_attempts = 0
}

resource "aws_cloudwatch_event_rule" "service" {
  name           = "coverage-orchestrator-${var.environment}"
  event_bus_name = data.terraform_remote_state.core.outputs.coverage_event_bus.name

  event_pattern = <<EOF
  {
    "detail-type": [
      "INGEST_STARTED",
      "INGEST_SUCCESS",
      "INGEST_FAILURE",
      "JOB_STATE_CHANGE",
      "CONFIGURATION_FILE_CHANGE",
      "COVERAGE_FINALISED"
    ]
  }
  EOF
}

resource "aws_cloudwatch_event_target" "service" {
  event_bus_name = data.terraform_remote_state.core.outputs.coverage_event_bus.name

  rule      = aws_cloudwatch_event_rule.service.name
  target_id = "coverage-orchestrator-service"
  arn       = aws_lambda_function.service.arn
}

resource "aws_lambda_permission" "lambda_permissions" {
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.service.function_name
  principal     = "events.amazonaws.com"
  source_arn    = aws_cloudwatch_event_rule.service.arn
}