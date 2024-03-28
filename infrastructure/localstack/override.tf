terraform {
  backend "local" {
    path          = "./.localstack/localstack.tfstate"
    workspace_dir = "./.localstack/localstack.tfstate.d"
  }
}

// Turn off any GCP infrastructure which would normally be configured
// by the provider. This includes mocking out the GCP credentials, and not configuring
// the data warehouse.
provider "google" {
  credentials = "{\"type\":\"service_account\",\"project_id\":\"\",\"private_key_id\":\"\",\"private_key\":\"\",\"client_email\":\"\",\"client_id\":\"\",\"auth_uri\":\"\",\"token_uri\":\"\",\"auth_provider_x509_cert_url\":\"\",\"client_x509_cert_url\":\"\",\"universe_domain\":\"\"}"
}

// Turn off any non-essential services which aren't mocked via Localstack

module "warehouse" {
  source = "./warehouse"
  count  = 0
}

module "database" {
  source = "./database"
  count  = 0
}

module "routing" {
  source = "./routing"
  count  = 0
}