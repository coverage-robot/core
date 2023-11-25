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
  default = "php-83"
}

variable "deployment_hash" {
  type = string
}

variable "event_store_arn" {
  type = string
}

variable "event_store_name" {
  type = string
}