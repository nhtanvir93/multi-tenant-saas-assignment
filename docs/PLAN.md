# PLAN — SaaS Subscription & Tenant Management API

Source of truth: `Assessment_1___SaaS_Subscription___Tenant_Management_API.pdf` (company spec). The ChatGPT spec is the same content restated in Bangla/Markdown; no conflicts. Keep both in `docs/specs/`.

## 1. Stack

- PHP 8.4, Laravel 13, Sanctum (token auth)
- PostgreSQL 16, Redis 7 (cache + queue + rate limiter)
- Docker Compose: `app` (php-fpm), `nginx`, `postgres`, `redis`, `queue` (worker), `scheduler`
- Tests: PHPUnit feature + unit tests, separate test DB, runs from clean `docker compose run`
- Docs: README + `docs/API.md` + Postman collection

## 2. Multi-tenancy (shared DB, `company_id` column)

- Tenant = `companies` row. Every tenant-owned table has `company_id` (FK, indexed, NOT NULL).
- `TenantContext` (scoped singleton) is set by `SetTenantContext` middleware **from the authenticated user's `company_id`, never from request input**.
- `BelongsToTenant` trait on models: adds `TenantScope` global scope (`where company_id = ctx`) and auto-fills `company_id` on create.
- Route model binding goes through the scope, so another tenant's ID returns **404** (no existence leak).
- Request validation: `exists`/`unique` rules are always tenant-scoped (custom `Rule::exists(...)->where('company_id', ...)`).
- Jobs carry `company_id` explicitly and set `TenantContext` on start.
- Cache keys are always prefixed `tenant:{id}:`.
- Trade-off documented: shared DB is simpler/cheaper at scale than DB-per-tenant; isolation enforced in app layer + tests.

## 3. Schema

Full design with indexes and reasons: `docs/DATABASE.md`.

```
plans(id, slug UQ, name, tier smallint UQ (rank: free=1, pro=2, enterprise=3), price_cents, max_users NULL=unlimited, max_customers NULL=unlimited, features jsonb, is_active, timestamps)
companies(id, name, slug UQ, timestamps)  -- owner = user with role='owner' (partial unique index), no circular FK, no soft delete (no delete endpoint)
subscriptions(id, company_id FK, plan_id FK, status[active|replaced|expired], starts_at, ends_at NULL, timestamps)  -- history kept: upgrade ends old row (replaced) and inserts a new active row
  idx (company_id, status); partial UQ: one active subscription per company
users(id, company_id FK, name, email UQ(global), password, role[owner|admin|user], status, timestamps)
  idx (company_id, role), (company_id, created_at)
customers(id, company_id FK, name, email, phone, status[active|inactive|lead], notes, timestamps, soft deletes)
  idx (company_id, status), (company_id, created_at), UQ (company_id, email)
  search: (company_id, name) prefix LIKE; pg_trgm GIN on name/email as optional optimisation
personal_access_tokens (Sanctum)
```

- FKs: `ON DELETE CASCADE` company->users/customers/subscriptions; `RESTRICT` plan->subscriptions.
- Pagination: offset for small pages with `per_page` capped at 100; select only needed columns; `simplePaginate` not needed because total is displayed.

## 4. Auth & RBAC

- `POST /auth/register-company` creates company + owner user + Free subscription in one DB transaction.
- `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` (Sanctum token).
- Role enum `Role::{Owner, Admin, User}`; Laravel Policies per model.

| Action                     | Owner | Admin                            | User                          |
| -------------------------- | ----- | -------------------------------- | ----------------------------- |
| Company view/update        | yes   | view                             | view                          |
| Subscription view          | yes   | yes                              | view usage only               |
| Subscription upgrade plan  | yes   | no                               | no                            |
| Users create/update/delete | yes   | yes (not owner/admin-above-self) | no                            |
| Users list                 | yes   | yes                              | yes (read-only)               |
| Customers CRUD             | yes   | yes                              | create/read/update, no delete |
| Dashboard                  | yes   | yes                              | summary only                  |

Further user rules: see section 5a.

## 5. Subscription limits

- `LimitEnforcer` service with Strategy per resource: `LimitCheck` interface -> `UserLimitCheck`, `CustomerLimitCheck` (add more without editing enforcer: Open/Closed).
- Enforcement inside a DB transaction with `lockForUpdate` on the company's subscription row, then authoritative `count()`, to avoid race conditions at the limit.
- Violation -> `PlanLimitExceededException` -> HTTP **422**/**403** with error code `PLAN_LIMIT_REACHED` (decision: 403 with code).
- `UsageService::forCompany()` returns `{users:{used,limit,percent}, customers:{...}}`.
- Plan change (owner only) is **upgrade-only**: target `tier` must be greater than the current `tier`. Downgrade is always rejected (see 5a).

## 5a. Business rules & restrictions (mandatory; owner requirement)

Each rule has a stable error code, is enforced in a service (not only in validation) and has a test.

### Subscription

| Rule                    | Behaviour                                                                                                                                                                                                        | Code / status               |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------- |
| Upgrade only            | Plan change allowed only if `target.tier > current.tier` (Free -> Pro -> Enterprise). Reason: a lower limit cannot hold data already created under a higher one (10 users -> 5 users is impossible to maintain). | `DOWNGRADE_NOT_ALLOWED` 422 |
| No same-plan change     | Choosing the current plan is rejected                                                                                                                                                                            | `ALREADY_ON_PLAN` 422       |
| Owner only              | Only the Owner may change the plan                                                                                                                                                                               | `FORBIDDEN` 403             |
| Immediate effect        | Upgrade applies at once: new limits usable in the same second; old subscription row -> `replaced`, new row `active` (one DB transaction, `lockForUpdate` on company)                                             | n/a                         |
| One active subscription | Partial unique index on `(company_id) WHERE status='active'`                                                                                                                                                     | DB constraint               |
| History kept            | Subscription rows are never updated in place or deleted                                                                                                                                                          | n/a                         |
| Inactive plan           | A plan with `is_active=false` cannot be chosen                                                                                                                                                                   | `PLAN_NOT_AVAILABLE` 422    |
| No cancel endpoint      | Not in spec; out of scope (documented trade-off)                                                                                                                                                                 | n/a                         |
| Payment                 | Not in spec; plan change is simulated, no gateway (documented assumption)                                                                                                                                        | n/a                         |
| Enterprise limits       | Assumption: `NULL` = unlimited (usage percent returned as `null`). Confirm with owner                                                                                                                            | n/a                         |

### Plan limits

- Free 5 users / 100 customers; Pro 50 / 5,000; Enterprise unlimited (assumption).
- Creation at/over the limit is rejected: `PLAN_LIMIT_REACHED` 403, body includes `{resource, used, limit, plan}`.
- Counted resources: users = non-deleted users (inactive users still occupy a seat); customers = non-soft-deleted customers.
- Limit check + insert happen in one transaction with a row lock (no race past the limit).

### Users

- Exactly one Owner per company; nobody can create another Owner (ownership transfer is out of scope).
- Owner cannot be deleted, demoted or deactivated: `OWNER_PROTECTED` 403.
- Role creation: Owner may create Admin/User; Admin may create User only.
- Admin cannot modify or delete the Owner or other Admins; only Owner manages Admins.
- Nobody can delete themselves or change their own role: `SELF_ACTION_FORBIDDEN` 403.
- Email: globally unique, stored lowercase, case-insensitive uniqueness.
- Inactive user cannot log in; deactivation, delete, role change and password change revoke all that user's tokens.
- Users are hard-deleted (tokens cascade); customers are soft-deleted.

### Customers

- Email unique per company among non-deleted rows (partial unique index `WHERE deleted_at IS NULL`).
- Delete: Owner/Admin only; User role cannot delete.
- Soft delete frees a slot in the plan limit; restore is not exposed in the API.

### Company

- Slug is immutable after registration; only `name` can be updated.
- No company delete endpoint (out of scope, documented).
- Registration is one transaction: company + owner + Free subscription (all or nothing).

### Auth & API hygiene

- Generic login failure message (no user enumeration); login throttled (5/min per IP+email).
- Password: min 8 chars, hashed; token expiry configured; logout revokes the current token only.
- API always answers JSON (`Accept: application/json` forced); no stack traces (`APP_DEBUG=false` in prod); security headers; CORS configured via env.
- `per_page` capped at 100; unknown sort/filter fields rejected with 422 (whitelist).
- All timestamps UTC, ISO-8601 in responses; `password`, tokens never serialised.

### New error codes

`DOWNGRADE_NOT_ALLOWED`, `ALREADY_ON_PLAN`, `PLAN_NOT_AVAILABLE`, `OWNER_PROTECTED`, `SELF_ACTION_FORBIDDEN`, plus earlier `PLAN_LIMIT_REACHED`.

## 6. API (prefix `/api/v1`)

```
POST   /auth/register-company | /auth/login | /auth/logout ; GET /auth/me
GET    /company ; PUT /company
GET    /plans
GET    /subscription ; PUT /subscription (upgrade only) ; GET /subscription/usage
GET|POST /users ; GET|PUT|DELETE /users/{id}
GET|POST /customers ; GET|PUT|DELETE /customers/{id}
GET    /dashboard
```

- Response envelope: `{success, message, data, meta?}`; errors `{success:false, message, error:{code, details?}}`.
- Status codes: 200/201/204/401/403/404/422/429/500, via a central exception handler.
- Filtering: customers `status`, `search`, `sort`, `per_page`, `page`; users `role`, `search`.
- FormRequests for validation, API Resources for output, `X-Request-Id` header.

## 7. Caching (Redis)

| Key                                         | Data                | TTL | Invalidated on                                         |
| ------------------------------------------- | ------------------- | --- | ------------------------------------------------------ |
| `plans:all`                                 | plan catalogue      | 24h | plan seed/update                                       |
| `tenant:{id}:subscription`                  | subscription + plan | 1h  | plan change                                            |
| `tenant:{id}:usage`                         | usage counts        | 10m | user/customer create/delete/restore, plan change       |
| `tenant:{id}:dashboard`                     | dashboard payload   | 5m  | anything above + customer status change                |
| `tenant:{id}:customers:v{ver}:{md5(query)}` | list pages          | 5m  | bump `tenant:{id}:customers:ver` on any customer write |

- Pattern: cache-aside via `CacheService::remember()`; versioned keys avoid `KEYS`/pattern deletes.
- Invalidation: Eloquent Observers dispatch domain events; `InvalidateTenantCache` listener forgets/bumps keys (after commit: `ShouldHandleEventsAfterCommit`).
- Stampede protection: `Cache::lock` around dashboard rebuild.
- `CachedCustomerRepository` decorates `EloquentCustomerRepository` (Decorator pattern).

## 8. Background jobs (queue: redis)

1. `SendWelcomeEmail` / `SendUserInvitation` on company/user creation
2. `NotifyLimitThreshold` when usage crosses 80% / 100%
3. `WarmTenantCache` after bulk changes / plan change
4. `ExportCustomersCsv` (async export, stored to disk, notify) — stretch

- Document why each is async; retries + backoff + `failed_jobs`.

## 9. Architecture & patterns

Full detail: `docs/ARCHITECTURE.md`.

- Controllers thin -> FormRequest -> Service -> Eloquent/Repository -> Resource.
- Services: `AuthService`, `CompanyRegistrationService`, `UserService`, `CustomerService`, `SubscriptionService`, `UsageService`, `LimitEnforcer`, `DashboardService`, `CacheService`.
- Repositories **only** for Customer (heavy filtering + cached decorator). Everything else uses Eloquent directly; justification goes in README.
- Patterns used and why: Strategy (limits), Decorator (cached repo), Observer (cache invalidation), Policy (authorization), Global Scope (tenancy), DTO/FormRequest (input), Enum (roles/status).
- SOLID mapping documented in README.

## 10. Security

- Rate limiting: `auth` limiter 5/min per IP+email; `api` limiter 60/min per user; stricter for exports.
- Password hashing (bcrypt/argon), token expiry, validation everywhere, mass-assignment `$fillable`, no `company_id` in fillable.
- No tenant ID accepted from client; cross-tenant -> 404.

## 11. Testing (PHPUnit)

- Auth: register, login, logout, bad creds, throttling, unauthenticated 401
- Authorization: role matrix per endpoint
- Tenant isolation: A cannot list/view/update/delete B's users/customers; ID guessing -> 404; cache keys not shared
- Limits: Free 5 users / 100 customers; boundary at limit; race-safe; downgrade guard
- Caching: hit/miss, invalidation after create/update/delete
- Validation + error envelope; pagination/filter; jobs dispatched (`Queue::fake`)

## 12. Docker (single command)

```
docker compose up --build   # first run: builds image, installs deps, migrates, seeds
docker compose up           # every later run
```

| Service     | Role                                                                 |
| ----------- | -------------------------------------------------------------------- |
| `app`       | php-fpm; the **only** container that bootstraps (deps, key, migrate) |
| `nginx`     | HTTP entry, http://localhost:8000                                    |
| `postgres`  | DB; init script also creates `app_testing`                           |
| `redis`     | cache, queue, rate limiter                                           |
| `queue`     | `php artisan queue:work`, waits for `app` healthy                    |
| `scheduler` | `php artisan schedule:work`, waits for `app` healthy                 |

`docker/entrypoint.sh` (app only), in order:

1. copy `.env.example` -> `.env` if missing
2. wait for postgres + redis
3. `composer install` if `vendor/` is missing **or** `sha256(composer.lock)` differs from the hash stored in the vendor volume
4. `php artisan key:generate` if `APP_KEY` is empty
5. `php artisan migrate --force`; seed only if `plans` is empty
6. write ready marker -> healthcheck turns healthy -> `queue`/`scheduler` start

Volumes: source bind-mounted (`.:/var/www/html`); `vendor` in a named volume (fast, no host/OS mismatch, shared by app/queue/scheduler); `pgdata`, `redisdata`.

### Package management

| Need                                       | Command                                                                                              | Rebuild image?                               |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| Add PHP package                            | `docker compose exec app composer require vendor/pkg` (`--dev` for dev-only)                         | No. Commit `composer.json` + `composer.lock` |
| Teammate/reviewer pulls a new package      | `docker compose up` (entrypoint sees lock hash changed, runs `composer install`)                     | No                                           |
| New PHP extension / system lib             | edit `Dockerfile`                                                                                    | Yes: `docker compose up --build`             |
| New npm package (starter-kit tooling only) | `docker compose exec app npm install pkg`; entrypoint runs `npm ci` when `package-lock.json` changes | No                                           |

Makefile shortcuts: `make up`, `make down`, `make test`, `make fresh` (migrate:fresh --seed), `make artisan c="route:list"`, `make composer c="require x/y"`, `make logs`.
Tests: `make test` = `docker compose exec app php artisan test`; uses `app_testing` DB and Redis DB index 1. Cache tests use real Redis; others may use the array driver.
Prod note (documented only): Dockerfile multi-stage `prod` target with `composer install --no-dev --optimize-autoloader` baked in.

### Baseline note (owner's working setup)

The owner's repo is the source of truth for Docker/config files. Image: PHP 8.4 + Node 24 + gd/mbstring/xml. Tests read `.env.testing` + `phpunit.xml` (target DB `app_testing`, Redis DB 2/3). `config/auth.php` declares the `sanctum` guard. CI: `.github/workflows/tests.yaml`.

## 13. Delivery roadmap: 17 steps = 17 commits

Order requested by owner: plan -> design patterns -> schema -> API, then CRUD by CRUD. Each step is small enough to be one commit and is shown to the company as one logical unit. Migrations are written **with the CRUD that needs them** (schema is designed up front in step 2).

| #   | Step                                   | Scope                                                                                                                                        | Tests in this step                                                                        | Commit message                                                          |
| --- | -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| 0   | Plan & handoff docs                    | specs, PLAN, CONVENTIONS, PROGRESS, RESUME                                                                                                   | n/a                                                                                       | `docs: add specs, project plan and handoff docs`                        |
| 1   | Architecture & design patterns         | `docs/ARCHITECTURE.md`: layers, folders, patterns + justification, SOLID mapping, tenancy flow                                               | n/a                                                                                       | `docs: add architecture and design pattern decisions`                   |
| 2   | Schema design                          | `docs/DATABASE.md`: ERD, tables, constraints, every index with reason                                                                        | n/a                                                                                       | `docs: add database schema design`                                      |
| 3   | Docker + scaffold                      | Laravel 13, Dockerfile, compose, entrypoint, Makefile, `.env.example`, response envelope, exception handler, `GET /health`, phpunit config   | health, envelope shape                                                                    | `chore: scaffold Laravel with single-command Docker setup`              |
| 4   | Plans (read)                           | migration, model, seeder (Free/Pro/Enterprise), `GET /plans`                                                                                 | list, seed values                                                                         | `feat(plans): add plans catalogue endpoint`                             |
| 5   | Tenancy core                           | `TenantContext`, `TenantScope`, `BelongsToTenant`, `SetTenantContext`                                                                        | scope filters, auto-fill, no context = fail closed                                        | `feat(tenancy): add tenant context and global scope`                    |
| 6   | Auth + company registration            | migrations companies/users/subscriptions/tokens, `register-company` (transaction: company+owner+Free sub), login, logout, me, login throttle | register, login, logout, bad creds, 401, validation, throttle                             | `feat(auth): add company registration and token authentication`         |
| 7   | Company CRUD                           | `GET/PUT /company`, `Role` enum, `CompanyPolicy`                                                                                             | authz per role, validation                                                                | `feat(company): add company management with role policies`              |
| 8   | Subscription & usage                   | `GET /subscription`, `PUT /subscription` (owner, upgrade-only), `GET /subscription/usage`, `UsageService`, `LimitEnforcer` + strategies      | limit unit tests, upgrade ok, downgrade rejected, same-plan rejected, history rows, authz | `feat(subscription): add plans assignment, usage and limit enforcement` |
| 9   | Users CRUD                             | migration tweaks, service, FormRequests, resources, filters, pagination, role rules, limit check                                             | CRUD, role matrix, tenant isolation, user limit boundary                                  | `feat(users): add user management with RBAC and plan limits`            |
| 10  | Customers CRUD                         | migration, `CustomerRepository`, filters/search/sort, pagination, limit check                                                                | CRUD, filters, pagination, isolation, customer limit boundary                             | `feat(customers): add customer management with filtering and limits`    |
| 11  | Dashboard API                          | `GET /dashboard` (DB-backed, no cache yet)                                                                                                   | payload, percentages, role visibility                                                     | `feat(dashboard): add analytics endpoint`                               |
| 12  | Redis caching + invalidation           | `CacheService`, versioned keys, observers/events/listeners, `CachedCustomerRepository`, locks                                                | hit/miss, invalidation after each write type, tenant key separation                       | `feat(cache): add Redis caching with event-driven invalidation`         |
| 13  | Background jobs                        | welcome/invitation email, limit-threshold notify, cache warm                                                                                 | `Queue::fake` dispatch, job handles tenant context, retries                               | `feat(jobs): add queued jobs for notifications and cache warming`       |
| 14  | Rate limiting, hardening, query review | named limiters, `preventLazyLoading`, `EXPLAIN` review, index check                                                                          | 429 behaviour, query-count assertions (N+1)                                               | `perf(security): add rate limiting and query optimisation pass`         |
| 15  | API documentation                      | `docs/API.md` (requests, success + error samples), Postman collection                                                                        | n/a                                                                                       | `docs(api): add API reference and Postman collection`                   |
| 16  | README + final verification            | all README sections from spec, clean-clone run of `docker compose up` + `make test`, submission checklist                                    | full suite                                                                                | `docs: add README and final submission checklist`                       |

### Step Definition of Done (every step)

1. Code for that step only; no unrelated changes
2. Tests for that step written; `make test` green (or the AI states clearly it could not run them)
3. Relevant README/doc section added or extended (README grows with the code, not at the end)
4. `docs/PROGRESS.md` updated
5. Suggested commit message given (table above); AI **stops and waits** for the owner to commit and say "next"

### Within each CRUD step, in order

migration -> model/factory -> repository (only if planned) -> service -> FormRequest -> policy -> resource -> controller + route -> tests.
