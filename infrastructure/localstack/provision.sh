#!/bin/bash
# The Localstack mount script which automatically bootstraps the localstack environment
# with the global infrastructure defined in the terraform files.

function configure_files {
    mkdir -p /usr/infrastructure
    cp -r /tmp/infrastructure/* /usr/infrastructure
    cp /usr/infrastructure/localstack/override.tf /usr/infrastructure/localstack_override.tf
    rm -rf /usr/infrastructure/.terraform || true
}

function install_terraform {
  apt-get update && apt-get install -y lsb-release wget && apt-get clean all

  wget -O- https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg
  echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/hashicorp.list
  apt update && apt install terraform

  pip install terraform-local
}

function apply_infrastructure {
    cd /usr/infrastructure || return

    tflocal init -upgrade
    tflocal workspace select -or-create dev
    tflocal apply -auto-approve -lock=false -var-file="dev.tfvars"
}

install_terraform
configure_files
apply_infrastructure