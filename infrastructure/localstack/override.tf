terraform {
  backend "local" {
    path = "./.localstack"
    workspace_dir = "./.localstack/localstack.tfstate.d"
  }
}