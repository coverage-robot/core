variable "aws_region" {
  type    = string
  default = "eu-west-2"
}

variable "gcp_region" {
  type    = string
  default = "europe-west2"
}

variable "gcp_project" {
  type = string
}

variable "environment" {
  type    = string
  default = ""
}