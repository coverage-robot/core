# Orchestrator

The Orchestrator service manages the orchestration of job state changes, uploads and analysis, in order to
trigger the publishing of coverage results when provider's pipelines are believed to have finished.

## Deployment

Deployment is handled through Terraform, and is deployed as part of the CI/CD pipeline.

For the deployment of the Orchestrator service, see the [`infrastructure/`](./infrastructure) folder.