variable "region" {
  type    = string
  default = "eu-west-2"
}

variable "environment" {
  type    = string
  default = ""
}

variable "php_version" {
  type    = string
  default = "php-84"
}

variable "deployment_hash" {
  type = string
}

variable "ref_metadata_table" {}