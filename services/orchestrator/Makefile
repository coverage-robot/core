all:

invoke:
	docker compose -f ../../docker-compose.yml run -ePHP_IDE_CONFIG=serverName=orchestrator --rm orchestrator php -dxdebug.mode=debug -dxdebug.start_with_request=yes bin/console app:invoke -vv