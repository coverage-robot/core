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

resource "aws_iam_role" "ingest_role" {
  name = "ingest-role"
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

resource "aws_iam_policy" "ingest_policy" {
  name = "ingest-policy"
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
          "s3:GetObject",
          "s3:DeleteObject"
        ]
        Resource = [
          "${data.terraform_remote_state.core.outputs.ingest_bucket.arn}/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "s3:PutObject"
        ]
        Resource = [
          "${data.terraform_remote_state.core.outputs.output_bucket.arn}/*"
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
      },
      {
        Effect = "Allow"
        Action = [
          "dynamodb:Query"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.configuration_table.arn
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.ingest_role.name
  policy_arn = aws_iam_policy.ingest_policy.arn
}

resource "aws_lambda_function" "service" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-ingest-%s", var.environment)
  role             = aws_iam_role.ingest_role.arn
  runtime          = "provided.al2"
  handler          = "App\\Handler\\EventHandler"
  architectures    = ["arm64"]
  # Allow five minutes for the file to successfully ingest. That should be plenty of time to import hundreds of MB work of coverage
  timeout     = 300
  memory_size = 1024
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
      "BIGQUERY_PROJECT"             = data.terraform_remote_state.core.outputs.environment_dataset.project,
      "BIGQUERY_ENVIRONMENT_DATASET" = data.terraform_remote_state.core.outputs.environment_dataset.dataset_id,
      "BIGQUERY_LINE_COVERAGE_TABLE" = data.terraform_remote_state.core.outputs.line_coverage_table.table_id,
      "BIGQUERY_UPLOAD_TABLE"        = data.terraform_remote_state.core.outputs.upload_table.table_id,
      "AWS_ACCOUNT_ID"               = data.aws_caller_identity.current.account_id
    }
  }
}

resource "aws_lambda_function_event_invoke_config" "service_invoke_config" {
  function_name = aws_lambda_function.service.function_name

  # Don't retry events in the case of a timeout - its very likely that the event will have partially processed and we don't
  # want to duplicate the data
  maximum_retry_attempts = 0
}

resource "aws_lambda_permission" "allow_bucket" {
  statement_id  = "AllowExecutionFromS3Bucket"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.service.arn
  principal     = "s3.amazonaws.com"
  source_arn    = data.terraform_remote_state.core.outputs.ingest_bucket.arn
}

resource "aws_s3_bucket_notification" "ingest_trigger" {
  bucket = data.terraform_remote_state.core.outputs.ingest_bucket.id

  lambda_function {
    lambda_function_arn = aws_lambda_function.service.arn
    events              = ["s3:ObjectCreated:*"]
  }

  depends_on = [
    aws_lambda_permission.allow_bucket
  ]
}