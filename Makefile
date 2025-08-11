init:
	docker compose build && docker compose run --rm cli composer install
test:
	docker compose run --rm --remove-orphans cli vendor/bin/phpunit -v
shell:
	docker compose run --rm cli sh
