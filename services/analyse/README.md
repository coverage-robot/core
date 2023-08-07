# Analyse 
The Analyse service handles all of the analysis of the coverage data once its loaded into the internal architecture.

Once the analysis has been completed, the publishers are called to format and publish the results to varying destinations.

### Deployment
Deployment is handled through Terraform, and is deployed as part of the CI/CD pipeline.

For the deployment of the Analyse service, see the [`infrastructure/`](./infrastructure) folder.

### Local Development
All of the local containers are managed in the project root's [`docker-compose.yml`](../../docker-compose.yml), but to invoke ingestion you
can use the provided Makefile commands, using a Docker container.

#### Analyse Coverage Data
```makefile
make invoke commit=... pullRequest=... repository=... owner=... tag=... ref=... parent=...
```