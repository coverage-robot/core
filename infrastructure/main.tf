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
    environment    = terraform.workspace
    region         = "eu-west-2"
    logging_policy = {
        Effect = "Allow"
        Action = [
            "logs:CreateLogGroup",
            "logs:CreateLogStream",
            "logs:PutLogEvents"
        ]
        Resource = ["arn:aws:logs:*:*:*"]
    }
}

module "queue" {
    source      = "./queue"
    environment = local.environment
}

module "bucket" {
    source      = "./bucket"
    environment = local.environment
}

module "ingest" {
    source      = "../services/ingest/infrastructure"
    environment = local.environment
    region      = local.region

    ingest_bucket = module.bucket.ingest_bucket
    output_bucket = module.bucket.output_bucket

    policy_statements = [
        local.logging_policy,
        {
            Effect = "Allow"
            Action = [
                "sqs:SendMessage"
            ]
            Resource = [
                module.queue.analysis_queue.arn
            ]
        }
    ]

    # The layer version needs to be kept inline with Bref's release, so that the layers
    # match the runtime. See https://runtimes.bref.sh/?region=eu-west-2&version=2.0.4.
    bref_layer_version = "21"
}

module "analyse" {
    source      = "../services/analyse/infrastructure"
    environment = local.environment
    region      = local.region

    analysis_queue = module.queue.analysis_queue

    policy_statements = [
        local.logging_policy
    ]

    # The layer version needs to be kept inline with Bref's release, so that the layers
    # match the runtime. See https://runtimes.bref.sh/?region=eu-west-2&version=2.0.4.
    bref_layer_version = "21"
}