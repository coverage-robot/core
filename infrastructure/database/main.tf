terraform {
  required_providers {
    planetscale = {
      source  = "koslib/planetscale"
      version = "~> 0.5"
    }
  }
}

resource "planetscale_database" "coverage_api_db" {
  organization = var.organisation
  name         = format("coverage-api-%s", var.environment)
  region       = var.region
}