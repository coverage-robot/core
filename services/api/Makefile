all:

migration:
	docker compose -f ../../docker-compose.yml exec api php bin/console make:migration

migrate:
	docker compose -f ../../docker-compose.yml exec api php bin/console doctrine:migrations:migrate

token:
	docker compose -f ../../docker-compose.yml exec api php bin/console app:new_token $(repository) $(owner) $(provider)