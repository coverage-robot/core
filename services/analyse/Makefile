all:

invoke:
	docker compose -f ../../docker-compose.yml run --rm analyse php bin/console app:invoke $(commit) $(pullRequest) $(repository) $(owner) $(tag) $(ref) $(parent) -vv
