terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    google = {
      source  = "hashicorp/google"
      version = "~> 4.65"
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
  region = var.aws_region
}

provider "google" {
  project = var.gcp_project
  region  = var.gcp_region
}

module "queue" {
  source      = "./queue"
  environment = local.environment
}

module "bucket" {
  source      = "./bucket"
  environment = local.environment
}

module "warehouse" {
  source      = "./warehouse"
  environment = local.environment
}