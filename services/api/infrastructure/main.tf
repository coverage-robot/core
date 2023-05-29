locals {
  bref_layers = jsondecode(file("${path.module}/../vendor/bref/bref/layers.json"))
  layer = format(
    "arn:aws:lambda:%s:534081306603:layer:${var.php_version}:%s",
    var.region,
    local.bref_layers[var.php_version][var.region]
  )
}

terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  required_version = ">= 1.2.0"

  backend "s3" {
    bucket         = "tf-coverage-state"
    key            = "state/api/terraform.tfstate"
    region         = "eu-west-2"
    encrypt        = true
    dynamodb_table = "tf-coverage-locks"
  }
}

provider "aws" {
  region = var.region
}

locals {
  environment = var.environment != "" ? var.environment : terraform.workspace
}

data "terraform_remote_state" "core" {
  backend = "s3"

  workspace = local.environment

  config = {
    bucket         = "tf-coverage-state"
    key            = "state/core/terraform.tfstate"
    region         = "eu-west-2"
    encrypt        = true
    dynamodb_table = "tf-coverage-locks"
  }
}

resource "aws_iam_role" "api_policy" {
  name = "api-service-policy"
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
  role       = aws_iam_role.api_policy.name
  policy_arn = aws_iam_policy.api_service_policy.arn
}

data "archive_file" "deployment" {
  type        = "zip"
  source_dir  = "${path.module}/../"
  output_path = "${path.module}/deployment.zip"
  excludes = [
    "composer.lock",
    "README.md",
    "tests",
    "infrastructure"
  ]
}

resource "aws_lambda_function" "service" {
  filename         = "${path.module}/deployment.zip"
  source_code_hash = data.archive_file.deployment.output_base64sha256
  function_name    = format("coverage-api-%s", local.environment)
  role             = aws_iam_role.api_policy.arn
  runtime          = "provided.al2"
  handler          = "public/index.php"
  architectures    = ["arm64"]
  timeout          = 28
  layers           = [local.layer]
}

resource "aws_lambda_function_url" "service_url" {
  authorization_type = "NONE"
  function_name      = aws_lambda_function.service.function_name
  cors {
    allow_methods = ["GET"]
    allow_origins = ["*"]
  }
}