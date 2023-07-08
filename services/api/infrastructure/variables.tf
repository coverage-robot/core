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
  default = "arm-php-82-fpm"
}

variable "database_username" {
  type = string
}

variable "database_password" {
  type      = string
  sensitive = true
}
variable "database_host" {
  type    = string
  default = "aws.connect.psdb.cloud"
}