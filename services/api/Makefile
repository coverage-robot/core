all:

token:
	docker compose -f ../../docker-compose.yml exec -ePHP_IDE_CONFIG=serverName=api api php -dxdebug.mode=debug -dxdebug.start_with_request=yes bin/console app:new_project $(repository) $(owner) $(provider)

invoke_ingest_success:
	docker compose -f ../../docker-compose.yml exec -ePHP_IDE_CONFIG=serverName=api api php -dxdebug.mode=debug -dxdebug.start_with_request=yes bin/console app:invoke_event INGEST_SUCCESS '{"upload": { "uploadId": "mock-upload", "provider": "$(provider)","repository": "$(repository)", "owner": "$(owner)", "ref": "$(ref)",  "pullRequest": "$(pullRequest)", "commit": "$(commit)", "parent": "[\"$(parent)\"]", "tag": "$(tag)" }, "coveragePercentage": $(coveragePercentage) }' -v