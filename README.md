# Coverage Robot
![Coverage](https://api.coveragerobot.com/v1/graph/github/coverage-robot/core/badge.svg?token=88f90351b6ac5ff3b37dec111714c05195803089cdba6892cc)

The core platform for ingesting, analysing, and outputting coverage data onto Version Control platforms.

## Architecture
![architecture.jpg](resources%2Farchitecture.jpg)

## Folder Structure
- [`infastructure/`](infrastructure/)

The _global_ IaC modules to construct the static parts of the infrastructure (e.g. S3 buckets, event buses, caches, etc).

- [`packages/`](packages/)

The _shared_ packages that are used across the microservices.

- [`services/`](services/)

The _independent_ services which are deployed to handle parts of the pipeline.

## Deployment
Theres two key parts of the deployment model of the coverage platform, both of which are handled by Terraform.

Broadly those are:
1. The _global_ infrastructure which exists outside of any particular service.
2. The _service specific_ infrastructure, which is managed by the service itself, and deployed _alongside_ the service.