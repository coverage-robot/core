# Ingest
The Ingest service handles all of the ingestion portion of the pipeline, and is responsible for taking the files, divining the coverage format, and storing the results in the warehouse, S3, and event bus.

## Deployment
Deployment is handled through Terraform, and is deployed as part of the CI/CD pipeline.

For the deployment of the API, see the [`infrastructure/`](./infrastructure) folder.

## Local Development

All of the local containers are managed in the project root's [`docker-compose.yml`](../../docker-compose.yml), but to invoke ingestion you
can use the provided Makefile commands, using a Docker container.

### Invoke Ingestion

Perform a PUT request to push the file into S3 locally:
```makefile
make put_file file=... provider=... owner=... repository=... commit=... pullRequest=... tag=... ref=... parent=...
```

Invoke the ingestion service to process the file:
```makefile
make invoke file=...
```