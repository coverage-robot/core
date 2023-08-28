all:

invoke:
	docker compose -f ../../docker-compose.yml run -ePHP_IDE_CONFIG=serverName=analyse --rm analyse php -dxdebug.mode=debug -dxdebug.start_with_request=yes bin/console app:invoke $(commit) $(pullRequest) $(repository) $(owner) $(tag) $(ref) $(parent) -vv
