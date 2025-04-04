variable "environment" {
  type    = string
  default = "dev"
}

variable "certificate_arn" {
  type      = string
  sensitive = true
}