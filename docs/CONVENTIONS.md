# CONVENTIONS

## Non-negotiables (from the spec)

- Every tenant-owned query is scoped by `company_id` (global scope). Never accept `company_id` from clients.
- Limits are enforced in service code, not only stored in DB.
- Every list endpoint paginates (`per_page` max 100) and eager loads relations. `Model::preventLazyLoading()` is on in non-production.
- Cache: cache-aside, keys prefixed `tenant:{id}:`, TTL always set, invalidated via observers/events.
- No unnecessary abstraction: repository only for Customer; no interface without 2 implementations or a test seam.
- Controllers: validate (FormRequest) -> authorize (Policy) -> call service -> return Resource. No business logic.

## Code style

- `declare(strict_types=1);`, PSR-12 (Laravel Pint), typed properties/returns, enums for role/status.
- Namespaces: `App\Services`, `App\Repositories`, `App\Http\{Controllers,Requests,Resources,Middleware}`, `App\Policies`, `App\Jobs`, `App\Events`, `App\Listeners`, `App\Observers`, `App\Support\Tenancy`, `App\Exceptions`.
- Response envelope: `{success, message, data, meta?}` / errors `{success:false, message, error:{code, details?}}`.
- API prefix `/api/v1`, plural nouns, snake_case JSON.
- Error codes: `VALIDATION_ERROR`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `PLAN_LIMIT_REACHED`, `RATE_LIMITED`, `DOWNGRADE_NOT_ALLOWED`, `ALREADY_ON_PLAN`, `PLAN_NOT_AVAILABLE`, `OWNER_PROTECTED`, `SELF_ACTION_FORBIDDEN`.
- Tests: one feature test file per resource; name `test_<behaviour>`; factories for everything; no shared mutable state.

## Business rules

- `docs/PLAN.md` section 5a is mandatory. Each rule is enforced in a service (not just a FormRequest), has a stable error code, and has a test in the step that implements it.
- Subscriptions are upgrade-only; subscription rows are history and are never edited in place.

## Delivery workflow (owner requirement: step by step, one commit per step)

- Follow the 17-step roadmap in `docs/PLAN.md` section 13, in order. Never batch steps.
- Order of thinking is fixed: plan -> design patterns -> schema -> API, then CRUD by CRUD.
- Inside a CRUD step: migration -> model/factory -> (repository) -> service -> FormRequest -> policy -> resource -> controller/route -> tests.
- Tests ship in the same step as the feature they cover.
- Each step adds its endpoints to `docs/API.md` (request, success sample, error samples).
- At the end of each step: update `docs/PROGRESS.md`, give the suggested commit message, then STOP and wait for the owner to commit and say "next".
- The owner commits; the AI does not squash steps or rewrite earlier steps. If an earlier step must change, do it as a new small step and log it in PROGRESS.
- Record any decision change in `docs/PLAN.md` (section + date).
- If the AI cannot execute PHP/Docker in its environment, it says so explicitly and gives the exact command for the owner to run (`make test`); it never claims tests passed unless it ran them.

## Docker rules

- Whole stack starts with `docker compose up --build` (first) / `docker compose up`. No manual host setup.
- Only the `app` container bootstraps (composer install, key, migrate, seed-if-empty). `queue`/`scheduler` wait for it to be healthy.
- Dependencies live in `composer.json` + `composer.lock` (both committed). Add packages with `docker compose exec app composer require vendor/pkg` (or `make composer c="require vendor/pkg"`). The entrypoint re-runs `composer install` when `composer.lock` changes, so no image rebuild.
- PHP extensions/system libs belong in the `Dockerfile` -> rebuild with `--build`.
- Never commit `vendor/`, `.env`, or volumes. Commit `.env.example` with every variable documented.
- Tests run inside Docker only: `make test`. Test settings live in `.env.testing` + `phpunit.xml` and must target the `app_testing` database, never the dev DB.
- The owner's repo is the source of truth for Docker/config files. Before changing `Dockerfile`, `docker/entrypoint.sh`, `phpunit.xml`, `.env.testing`, `config/*` or CI, read the current repo version; never re-deliver old copies over it.
- Tests must never run against a non-`*_testing` database: keep the guard in `tests/TestCase.php`. When a host-side file edit seems ignored, check the container's view (`docker compose exec app cat <file>`); edit via `docker compose exec app sed -i ...` or restart `app`.
- `auth:sanctum` requires the `sanctum` guard declared in `config/auth.php` (already present).
