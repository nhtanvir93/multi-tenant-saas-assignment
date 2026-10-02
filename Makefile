.DEFAULT_GOAL := help
export UID := $(shell id -u)
export GID := $(shell id -g)

DC  := docker compose
APP := $(DC) exec app

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  %-10s %s\n", $$1, $$2}'

scaffold: ## One-time: generate the Laravel skeleton (needs only Docker)
	sh scripts/scaffold.sh

up: ## Build and start everything in the background
	$(DC) up -d --build

down: ## Stop containers (data volumes are kept)
	$(DC) down

reset: ## Stop and DELETE all volumes (database, redis, vendor)
	$(DC) down -v

logs: ## Follow app + queue logs
	$(DC) logs -f app queue

sh: ## Shell inside the app container
	$(APP) bash

test-db: ## Make sure the app_testing database exists
	@$(DC) exec -T postgres sh -c "psql -U \$$POSTGRES_USER -d postgres -tAc \"SELECT 1 FROM pg_database WHERE datname='app_testing'\" | grep -q 1 || psql -U \$$POSTGRES_USER -d postgres -c 'CREATE DATABASE app_testing'"

test: test-db ## Run tests (optional: make test args="--filter=Health")
	$(APP) php artisan test $(args)

fresh: ## Drop all tables, migrate and seed
	$(APP) php artisan migrate:fresh --seed

artisan: ## Run artisan: make artisan c="route:list"
	$(APP) php artisan $(c)

composer: ## Run composer: make composer c="require vendor/pkg"
	$(APP) composer $(c)

pint: ## Fix code style
	$(APP) ./vendor/bin/pint
