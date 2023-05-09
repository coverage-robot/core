terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 4.16"
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

provider "aws" {
  region = var.region
}

module "queue" {
  source      = "./queue"
  environment = var.environment
}

module "bucket" {
  source      = "./bucket"
  environment = var.environment
}