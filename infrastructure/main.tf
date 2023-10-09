terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    google = {
      source  = "hashicorp/google"
      version = "~> 5.0"
    }
    planetscale = {
      source  = "koslib/planetscale"
      version = "~> 0.5"
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

provider "planetscale" {
  service_token_id = var.planetscale_service_token_id
  service_token    = var.planetscale_service_token
}

module "routing" {
  source      = "./routing"
  environment = local.environment
}

module "events" {
  source      = "./events"
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

module "database" {
  source      = "./database"
  environment = local.environment

  organisation = var.planetscale_organisation
  region       = var.planetscale_region
}