locals {
  bref_layers           = jsondecode(file("${path.module}/../../vendor/bref/bref/layers.json"))
  bref_extension_layers = jsondecode(file("${path.module}/../../vendor/bref/extra-php-extensions/layers.json"))
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
          "s3:PutObject",
          "s3:PutObjectAcl"
        ]
        Resource = [
          "${data.terraform_remote_state.core.outputs.ingest_bucket.arn}/*"
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
  role       = aws_iam_role.api_role.name
  policy_arn = aws_iam_policy.api_policy.arn
}

resource "aws_lambda_function" "api" {
  filename         = "${path.module}/../deployment.zip"
  source_code_hash = var.deployment_hash
  function_name    = format("coverage-api-%s", var.environment)
  role             = aws_iam_role.api_role.arn
  runtime          = "provided.al2"
  handler          = "public/index.php"
  architectures    = ["x86_64"]
  timeout          = 28
  memory_size      = 1024
  layers = [
    format(
      "arn:aws:lambda:%s:534081306603:layer:${var.php_version}-fpm:%s",
      var.region,
      local.bref_layers["${var.php_version}-fpm"][var.region]
    ),
    format(
      "arn:aws:lambda:%s:403367587399:layer:gd-%s:%s",
      var.region,
      var.php_version,
      local.bref_extension_layers["gd-${var.php_version}"][var.region]
    )
  ]

  environment {
    variables = {
      BREF_PING_DISABLE = "1"
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
  route_key = "ANY /api/{proxy+}"

  target = "integrations/${aws_apigatewayv2_integration.integration.id}"
}

resource "aws_lambda_function_url" "api_url" {
  authorization_type = "NONE"
  function_name      = aws_lambda_function.api.function_name
  cors {
    allow_methods = ["GET"]
    allow_origins = ["*"]
  }
}