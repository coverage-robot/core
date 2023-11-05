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
  default = "php-82"
}

variable "deployment_hash" {
  type = string
}

variable "event_store_arn" {
  type = string
}