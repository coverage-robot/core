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
        key            = "state/terraform.tfstate"
        region         = "eu-west-2"
        encrypt        = true
        dynamodb_table = "tf-coverage-locks"
    }
}

provider "aws" {
    region = local.region
}

locals {
    region = "eu-west-2"
}

module "remote" {
    # Manage the remote state for Terraform, using Terraform.
    source = "./remote"
}

module "core" {
    source      = "./core"
    region      = local.region
    environment = terraform.workspace
}