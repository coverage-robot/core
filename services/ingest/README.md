## Local Development

### Invoke Ingestion

Perform a PUT request to push the file into S3 locally:
```makefile
make put_file file=... provider=... owner=... repository=... commit=... pullRequest=... tag=... ref=... parent=...
```

Invoke the ingestion service to process the file:
```makefile
make invoke file=...
```