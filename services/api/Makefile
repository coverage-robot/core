all:

migration:
	docker compose -f ../../docker-compose.yml exec api php bin/console make:migration

migrate:
	docker compose -f ../../docker-compose.yml exec api php bin/console doctrine:migrations:migrate

token:
	docker compose -f ../../docker-compose.yml exec api php bin/console app:new_project $(repository) $(owner) $(provider)

invoke_ingest_success:
	docker compose -f ../../docker-compose.yml exec api php bin/console app:invoke_event INGEST_SUCCESS '{"upload": { "uploadId": "mock-upload", "provider": "$(provider)","repository": "$(repository)", "owner": "$(owner)", "ref": "$(ref)",  "pullRequest": "$(pullRequest)", "commit": "$(commit)", "parent": "[\"$(parent)\"]", "tag": "$(tag)" }, "coveragePercentage": $(coveragePercentage) }' -v