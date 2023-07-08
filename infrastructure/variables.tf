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

variable "planetscale_service_token_id" {
  type      = string
  sensitive = true
}

variable "planetscale_service_token" {
  type      = string
  sensitive = true
}

variable "planetscale_organisation" {
  type = string
}

variable "environment" {
  type    = string
  default = ""
}