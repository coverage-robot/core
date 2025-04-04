
provider "aws" {
  region = "us-east-1"
}

resource "aws_acm_certificate" "certificate" {
  domain_name               = "coveragerobot.com"
  validation_method         = "DNS"
  subject_alternative_names = ["*.coveragerobot.com"]

  lifecycle {
    create_before_destroy = true
  }
}