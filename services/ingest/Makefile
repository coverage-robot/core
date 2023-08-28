all:

put_file:
	awslocal s3 cp $(file) s3://coverage-ingest-dev --metadata '{\"provider\":\"$(provider)\",\"commit\":\"$(commit)\",\"parent\":\"[\\\"$(parent)\\\"]\",\"repository\":\"$(repository)\",\"owner\":\"$(owner)\",\"tag\":\"$(tag)\",\"uploadid\":\"$(uploadId)\"}'

invoke:
	docker-compose -f ../../docker-compose.yml run -ePHP_IDE_CONFIG=serverName=ingest --rm ingest php -dxdebug.mode=debug -dxdebug.start_with_request=yes bin/console app:invoke $(file) -vv