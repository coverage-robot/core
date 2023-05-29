terraform {
  backend "local" {
    path = "./.localstack/localstack.tfstate"
    workspace_dir = "./.localstack/localstack.tfstate.d"
  }
}