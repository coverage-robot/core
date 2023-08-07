# API
The API is the public entrypoint of the platform. It handles generating signed upload URLs to S3 for new coverage files,
and handles the badge requests.

### Deployment
Deployment is handled through Terraform, and is deployed as part of the CI/CD pipeline.

For the deployment of the API, see the [`infrastructure/`](./infrastructure) folder.

### Local Development

All of the local containers are managed in the project root's [`docker-compose.yml`](../../docker-compose.yml), but to run
requests locally, you can use the provided Makefile commands, and send requests to the Docker container.

#### Sign Upload
```
POST http://localhost:8000/upload
```

#### Create a Project
```makefile
make token provider=... owner=... repository=...
```

#### Migrations

To make a new migration file:
```makefile
make migration
```

To execute any unapplied migrations:
```makefile
make migrate
```