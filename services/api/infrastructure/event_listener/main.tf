locals {
  bref_layers = jsondecode(file("${path.module}/../../vendor/bref/bref/layers.json"))
}

data "aws_caller_identity" "current" {}

data "terraform_remote_state" "core" {
  backend = "s3"

  workspace = var.environment

  config = {
    bucket       = "tf-coverage-state"
    key          = "state/core/terraform.tfstate"
    region       = "eu-west-2"
    encrypt      = true
    use_lockfile = true
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
          "dynamodb:DescribeTable",
          "dynamodb:Query",
          "dynamodb:PutItem"
        ]
        Resource = [
          var.ref_metadata_table.arn
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
  role       = aws_iam_role.api_policy.name
  policy_arn = aws_iam_policy.api_service_policy.arn
}

resource "aws_lambda_function" "events" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-api-event-listener-%s", var.environment)
  role             = aws_iam_role.api_policy.arn
  runtime          = "provided.al2"
  handler          = "Packages\\Event\\Handler\\EventHandler"
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
      BREF_PING_DISABLE    = "1"
      "AWS_ACCOUNT_ID"     = data.aws_caller_identity.current.account_id
      "REF_METADATA_TABLE" = var.ref_metadata_table.name,
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

