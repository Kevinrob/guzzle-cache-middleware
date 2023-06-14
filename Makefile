init:
	docker compose run cli composer i
test:
	docker compose run cli vendor/bin/phpunit
shell:
	docker compose run cli sh
