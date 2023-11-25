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

  # The Bref GD layer isn't currently available on the PHP 8.3 runtime, so we're leaving this as 8.2
  # for now. Tests, linting and all other checks pass with 8.3 currently, so in the near future this
  # will need to be bumped.
  default = "php-82"
}