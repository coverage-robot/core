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

resource "aws_iam_role" "analyse_role" {
  name               = "analyse-role"
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

resource "aws_iam_policy" "analyse_policy" {
  name   = "analyse-policy"
  path   = "/"
  policy = jsonencode({
    Version   = "2012-10-17"
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
      {
        Effect = "Allow"
        Action = [
          "sqs:SendMessage"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.publish_queue.arn
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.analyse_role.name
  policy_arn = aws_iam_policy.analyse_policy.arn
}

resource "aws_lambda_function" "analyse" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash

  function_name = format("coverage-analyse-%s", var.environment)
  role          = aws_iam_role.analyse_role.arn
  timeout       = 60
  memory_size   = 1024
  runtime       = "provided.al2"
  handler       = "App\\Handler\\EventHandler"
  architectures = ["arm64"]

  layers = [
    format(
      "arn:aws:lambda:%s:534081306603:layer:arm-${var.php_version}:%s",
      var.region,
      local.bref_layers["arm-${var.php_version}"][var.region]
    )
  ]

  environment {
    variables = {
      "EVENT_BUS"                    = data.terraform_remote_state.core.outputs.coverage_event_bus.name,
      "PUBLISH_QUEUE"                = data.terraform_remote_state.core.outputs.publish_queue.url,
      "BIGQUERY_PROJECT"             = data.terraform_remote_state.core.outputs.environment_dataset.project,
      "BIGQUERY_ENVIRONMENT_DATASET" = data.terraform_remote_state.core.outputs.environment_dataset.dataset_id,
      "BIGQUERY_LINE_COVERAGE_TABLE" = data.terraform_remote_state.core.outputs.line_coverage_table.table_id,
    }
  }
}


resource "aws_cloudwatch_event_rule" "service" {
  name           = "coverage-analyse-${var.environment}"
  event_bus_name = data.terraform_remote_state.core.outputs.coverage_event_bus.name

  event_pattern = <<EOF
  {
    "detail-type": [
      "INGEST_SUCCESS",
      "JOB_STATE_CHANGE"
    ]
  }
  EOF
}

resource "aws_cloudwatch_event_target" "event_listener" {
  event_bus_name = data.terraform_remote_state.core.outputs.coverage_event_bus.name

  rule      = aws_cloudwatch_event_rule.service.name
  target_id = "coverage-analyse-service"
  arn       = aws_lambda_function.analyse.arn
}

resource "aws_lambda_permission" "lambda_permissions" {
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.analyse.function_name
  principal     = "events.amazonaws.com"
  source_arn    = aws_cloudwatch_event_rule.service.arn
}