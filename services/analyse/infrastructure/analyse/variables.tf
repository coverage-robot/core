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

variable "query_cache_table" {
  type = string
}