terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    google = {
      source  = "hashicorp/google"
      version = "~> 6.0"
    }
  }

  required_version = ">= 1.2.0"

  backend "s3" {
    bucket       = "tf-coverage-state"
    key          = "state/core/terraform.tfstate"
    region       = "eu-west-2"
    encrypt      = true
    use_lockfile = true
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

# Manage the state bucket
module "state" {
  source = "./state"
}

module "certificate" {
  source      = "./certificate"
  environment = local.environment
}

module "routing" {
  source          = "./routing"
  environment     = local.environment
  certificate_arn = module.certificate.acm_certificate.arn
}

module "events" {
  source      = "./events"
  environment = local.environment
}

module "configuration" {
  source      = "./configuration"
  environment = local.environment
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

module "authentication" {
  source      = "./authentication"
  environment = local.environment
}
