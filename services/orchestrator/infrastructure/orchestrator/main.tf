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
          "events:PutEvents"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.coverage_event_bus.arn
        ]
      },
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

  environment {
    variables = {
      "EVENT_BUS" = data.terraform_remote_state.core.outputs.coverage_event_bus.name,
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
      "INGEST_SUCCESS",
      "INGEST_FAILURE",
      "JOB_STATE_CHANGE"
    ]
  }
  EOF
}

resource "aws_lambda_permission" "lambda_permissions" {
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.service.function_name
  principal     = "events.amazonaws.com"
  source_arn    = aws_cloudwatch_event_rule.service.arn
}