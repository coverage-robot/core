#!/bin/bash
# This script is mounted on the ready event of the localstack container, and will bootstrap
# the localstack environment with the infrastructure defined in the terraform files.

echo "--- Provisioning resources in Localstack environment ---"

echo "Configuring Terraform infrastructure directory."

# First, setup the infrastructure directory, with the required overrides
mkdir /usr/infrastructure
cp -r /tmp/infrastructure/* /usr/infrastructure
cp /usr/infrastructure/localstack/override.tf /usr/infrastructure/localstack_override.tf
rm -rf /usr/infrastructure/.terraform || true

echo "Setting up Terraform."

# Then, install Terraform binary
curl https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg --yes
echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/hashicorp.list
apt update && apt install terraform

# Then, install the tflocal wrapper command
pip install terraform-local

echo "Applying ephemeral infrastructure."

# Finally, apply the infrastructure
cd /usr/infrastructure

/usr/local/bin/tflocal init
/usr/local/bin/tflocal workspace select -or-create dev
/usr/local/bin/tflocal apply -auto-approve -lock=false

echo "--- Provisioning complete ---"