# Publish

The Publish service handles publishing information (known as messages) to third-party platforms, like GitHub, off the
back of queue items published by other services - such as PR results from the Analyse service.

## Deployment

Deployment is handled through Terraform, and is deployed as part of the CI/CD pipeline.

For the deployment of the Publish service, see the [`infrastructure/`](./infrastructure) folder.

## Local Development

All of the local containers are managed in the project root's [`docker-compose.yml`](../../docker-compose.yml), but to
invoke the publishing of messages, like PR comments, you can use the provided Makefile commands, using a Docker
container.

### Publishing Messages

Perform a PUT request to push the file into S3 locally:

```makefile
make put_file file=... provider=... owner=... repository=... commit=... pullRequest=... tag=... ref=... parent=...
```

Invoke the ingestion service to process the file:

```makefile
make invoke file=...
```