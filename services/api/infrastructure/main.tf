terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  required_version = ">= 1.2.0"

  backend "s3" {
    bucket       = "tf-coverage-state"
    key          = "state/api/terraform.tfstate"
    region       = "eu-west-2"
    encrypt      = true
    use_lockfile = true
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
    bucket       = "tf-coverage-state"
    key          = "state/core/terraform.tfstate"
    region       = "eu-west-2"
    encrypt      = true
    use_lockfile = true
  }
}

data "archive_file" "deployment" {
  type        = "zip"
  source_dir  = "${path.module}/../"
  output_path = "${path.module}/deployment.zip"
  excludes = [
    "composer.lock",
    "README.md",
    "tests",
    "infrastructure",
  ]
}

module "ref_metadata" {
  source      = "./ref_metadata"
  environment = local.environment
}

module "api" {
  source = "./api"

  php_version     = var.php_version
  deployment_hash = data.archive_file.deployment.output_base64sha256

  environment = local.environment
  region      = var.region

  ref_metadata_table = module.ref_metadata.ref_metadata_table
}

module "event_listener" {
  source = "./event_listener"

  php_version     = var.php_version
  deployment_hash = data.archive_file.deployment.output_base64sha256

  environment = local.environment
  region      = var.region

  ref_metadata_table = module.ref_metadata.ref_metadata_table
}

module "webhook_handler" {
  source = "./webhook_handler"

  php_version     = var.php_version
  deployment_hash = data.archive_file.deployment.output_base64sha256

  environment = local.environment
  region      = var.region
}

