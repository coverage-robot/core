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
    key            = "state/core/terraform.tfstate"
    region         = "eu-west-2"
    encrypt        = true
    dynamodb_table = "tf-coverage-locks"
  }
}

locals {
  environment = var.environment != "" ? var.environment : terraform.workspace
}

provider "aws" {
  region = var.region
}

module "queue" {
  source      = "./queue"
  environment = local.environment
}

module "bucket" {
  source      = "./bucket"
  environment = local.environment
}