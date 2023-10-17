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
  name = "api-event-listener-policy"
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
  name = "api-event-listener-policy"
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
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.api_policy.name
  policy_arn = aws_iam_policy.api_service_policy.arn
}

resource "aws_lambda_function" "events" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-api-event-listener-%s", var.environment)
  role             = aws_iam_role.api_policy.arn
  runtime          = "provided.al2"
  handler          = "App\\Handler\\EventHandler"
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

  environment {
    variables = {
      BREF_PING_DISABLE = "1"
    }
  }
}

resource "aws_cloudwatch_event_rule" "event_listener" {
  name           = "coverage-api-event-listener-${var.environment}"
  event_bus_name = data.terraform_remote_state.core.outputs.coverage_event_bus.name

  event_pattern = <<EOF
  {
    "detail-type": [
      "COVERAGE_FINALISED"
    ]
  }
  EOF
}

resource "aws_cloudwatch_event_target" "event_listener" {
  event_bus_name = data.terraform_remote_state.core.outputs.coverage_event_bus.name

  rule      = aws_cloudwatch_event_rule.event_listener.name
  target_id = "coverage-api-event-listener-service"
  arn       = aws_lambda_function.events.arn
}

resource "aws_lambda_permission" "lambda_permissions" {
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.events.function_name
  principal     = "events.amazonaws.com"
  source_arn    = aws_cloudwatch_event_rule.event_listener.arn
}