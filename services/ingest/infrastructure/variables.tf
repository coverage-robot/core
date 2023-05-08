variable "lambda_role" {}

variable "environment" {
    type = string
}

variable "region" {
    type = string
}

variable "bref_layer_version" {
    type = string
}

variable "ingest_bucket" {}

variable "output_bucket" {}