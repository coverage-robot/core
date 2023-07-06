all:

put_file:
	awslocal s3 cp $(file) s3://coverage-ingest-dev --metadata '{\"provider\":\"$(provider)\",\"commit\":\"$(commit)\",\"parent\":\"[\\\"$(parent)\\\"]\",\"repository\":\"$(repository)\",\"owner\":\"$(owner)\",\"tag\":\"$(tag)\",\"uploadid\":\"$(uploadId)\"}'

invoke:
	docker-compose -f ../../docker-compose.yml run --rm ingest php bin/console app:invoke $(file) -vv