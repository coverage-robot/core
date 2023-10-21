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
    key            = "state/analyse/terraform.tfstate"
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

data "archive_file" "deployment" {
  type        = "zip"
  source_dir  = "${path.module}/../"
  output_path = "${path.module}/deployment.zip"
  excludes    = [
    "composer.lock",
    "README.md",
    "tests",
    "infrastructure"
  ]
}

module "query_cache" {
  source      = "./query_cache"
  environment = local.environment
}

module "analyse" {
  source = "./analyse"

  php_version     = var.php_version
  deployment_hash = data.archive_file.deployment.output_base64sha256

  environment       = local.environment
  region            = var.region
  query_cache_table = module.query_cache.cache_table.name
}