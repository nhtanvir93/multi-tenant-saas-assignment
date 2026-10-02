# SaaS Subscription & Tenant Management API

Multi-tenant SaaS backend: Laravel 13, PostgreSQL 16, Redis 7, Docker.
Built step by step (one commit per step). Progress: `docs/PROGRESS.md`. Design: `docs/PLAN.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`.

## Quick start (Docker is the only requirement)

```bash
git clone <repo-url> && cd <repo>
docker compose up --build        # first run; later: docker compose up
```

The `app` container creates `.env`, installs dependencies, generates the app key, runs migrations and seeds reference data. Then open:
`http://localhost:8000/api/v1/health`

First time starting from an empty repo (no Laravel files yet): run `make scaffold` once, then the command above.

## Everyday commands (`make help`)

| Command                                | What it does                                                           |
| -------------------------------------- | ---------------------------------------------------------------------- |
| `make up` / `make down`                | start (detached, with build) / stop                                    |
| `make test`                            | run the test suite inside Docker (creates `app_testing` DB if missing) |
| `make fresh`                           | `migrate:fresh --seed`                                                 |
| `make artisan c="route:list"`          | run any artisan command                                                |
| `make composer c="require vendor/pkg"` | run composer inside the container                                      |
| `make reset`                           | stop and delete all volumes                                            |

## Managing packages with Docker

- **Add a PHP package:** `make composer c="require vendor/pkg"` (or `docker compose exec app composer require vendor/pkg`), then commit `composer.json` + `composer.lock`. No image rebuild.
- **Someone else added a package:** pull and run `docker compose up`. The entrypoint compares `composer.lock` with the last install and runs `composer install` automatically.
- **New PHP extension / system library:** edit `Dockerfile`, then `docker compose up --build`.
- Dependencies live in a Docker volume (`vendor`), so nothing needs installing on your machine.
- If your host user id is not 1000 (Linux): `export UID=$(id -u) GID=$(id -g)` before `docker compose up --build`, or use `make`.

## Services

| Service   | Purpose                                        | Host port |
| --------- | ---------------------------------------------- | --------- |
| nginx     | HTTP entry                                     | 8000      |
| app       | php-fpm, bootstraps the project                | none      |
| queue     | `queue:work` (Redis)                           | none      |
| scheduler | `schedule:work`                                | none      |
| postgres  | database (`app`, plus `app_testing` for tests) | 5433      |
| redis     | cache, queue, rate limiter                     | 6380      |

More sections (architecture, schema, caching, API docs...) are added as the steps are built.
