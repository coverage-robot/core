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

resource "aws_iam_role" "api_role" {
  name = "api-policy"
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

resource "aws_iam_policy" "api_policy" {
  name = "api-service-policy"
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
          "s3:PutObject",
          "s3:PutObjectAcl"
        ]
        Resource = [
          "${data.terraform_remote_state.core.outputs.ingest_bucket.arn}/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "sqs:GetQueueUrl",
          "sqs:SendMessage"
        ]
        Resource = [
          data.terraform_remote_state.core.outputs.webhooks_queue.arn
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.api_role.name
  policy_arn = aws_iam_policy.api_policy.arn
}

resource "aws_cognito_user_pool_client" "cognito_client" {
  name = format("coverage-api-%s", var.environment)

  user_pool_id = data.terraform_remote_state.core.outputs.project_pool.id

  generate_secret     = true
  explicit_auth_flows = ["ALLOW_ADMIN_USER_PASSWORD_AUTH", "ALLOW_REFRESH_TOKEN_AUTH"]
}

resource "aws_lambda_function" "api" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-api-%s", var.environment)
  role             = aws_iam_role.api_role.arn
  runtime          = "provided.al2"
  handler          = "public/index.php"
  architectures    = ["arm64"]
  timeout          = 28
  memory_size      = 1024
  layers = [
    format(
      "arn:aws:lambda:%s:534081306603:layer:arm-${var.php_version}-fpm:%s",
      var.region,
      local.bref_layers["arm-${var.php_version}-fpm"][var.region]
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
      "BREF_PING_DISABLE"          = "1"
      "AWS_ACCOUNT_ID"             = data.aws_caller_identity.current.account_id,
      "PROJECT_POOL_ID"            = data.terraform_remote_state.core.outputs.project_pool.id,
      "PROJECT_POOL_CLIENT_ID"     = aws_cognito_user_pool_client.cognito_client.id,
      "PROJECT_POOL_CLIENT_SECRET" = aws_cognito_user_pool_client.cognito_client.client_secret,
    }
  }
}

resource "aws_apigatewayv2_integration" "integration" {
  api_id           = data.terraform_remote_state.core.outputs.api_gateway.id
  integration_type = "AWS_PROXY"

  integration_method     = "POST"
  integration_uri        = aws_lambda_function.api.arn
  payload_format_version = "2.0"

  request_parameters = {
    "overwrite:path" = "$request.path.proxy"
  }
}

resource "aws_apigatewayv2_route" "route" {
  api_id    = data.terraform_remote_state.core.outputs.api_gateway.id
  route_key = "ANY /v1/{proxy+}"

  target = "integrations/${aws_apigatewayv2_integration.integration.id}"
}