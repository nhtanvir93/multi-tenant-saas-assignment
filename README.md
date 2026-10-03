# Multi-Tenant SaaS API

A production-oriented multi-tenant SaaS backend built with Laravel, PostgreSQL and Redis.

The project demonstrates a complete API architecture with:

- Multi-tenant data isolation
- Sanctum authentication
- Role-based access control
- Subscription plans and usage limits
- Service-layer business logic
- Repository abstraction where query complexity justifies it
- Redis caching and event-driven invalidation
- Background jobs
- API rate limiting
- Query/lazy-loading protection
- Automated feature and unit tests
- PHPStan/Larastan static analysis
- Laravel Pint code formatting
- Docker-based local development
- Postman API collection

---

## 1. Project Goals

The project is intentionally designed as a realistic multi-tenant SaaS backend rather than a collection of isolated CRUD endpoints.

The main architectural goals are:

1. Keep tenant data isolated by default.
2. Keep controllers thin.
3. Keep business rules inside services and dedicated domain components.
4. Use policies for authorization.
5. Enforce subscription limits independently from controllers.
6. Use PostgreSQL constraints and indexes to protect data integrity.
7. Use Redis only where caching/background processing provides a clear benefit.
8. Make queued jobs tenant-aware.
9. Detect accidental N+1/lazy-loading behaviour during development and testing.
10. Keep the codebase testable and suitable for static analysis.

---

## 2. Technology Stack

| Component       | Technology         |
| --------------- | ------------------ |
| Language        | PHP 8.4            |
| Framework       | Laravel 13         |
| Database        | PostgreSQL 16      |
| Cache           | Redis 7            |
| Authentication  | Laravel Sanctum    |
| Queue           | Laravel Queue      |
| Testing         | Pest / PHPUnit     |
| Static analysis | Larastan / PHPStan |
| Formatting      | Laravel Pint       |
| Web server      | Nginx              |
| Containers      | Docker Compose     |
| API format      | JSON REST API      |
| API client      | Postman            |

The project uses a shared PostgreSQL database with application-level tenant isolation.

---

## 3. High-Level Architecture

```text
                    HTTP Client
                        |
                        v
                  Nginx / API
                        |
                        v
              Laravel API Controllers
                        |
          +-------------+-------------+
          |                           |
          v                           v
    Form Requests                  Policies
          |                           |
          +-------------+-------------+
                        |
                        v
                    Services
                        |
          +-------------+-------------+
          |             |             |
          v             v             v
     Repositories    Limits        Cache
          |             |             |
          +-------------+-------------+
                        |
                        v
                   PostgreSQL
                        |
                        +------ Redis
                        |
                        +------ Queue
```

The application follows a layered architecture:

```text
HTTP
  ↓
Request / Authorization
  ↓
Controller
  ↓
Service
  ↓
Repository / Domain component
  ↓
Model / Database
```

Controllers are responsible for HTTP concerns. Business rules and transactions belong in services.

---

## 4. Project Structure

```text
app/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Listeners/
├── Limits/
├── Models/
├── Observers/
├── Policies/
├── Repositories/
│   ├── Contracts/
│   ├── EloquentCustomerRepository.php
│   └── CachedCustomerRepository.php
├── Services/
└── Support/
    ├── ApiResponse.php
    ├── Cache/
    └── Tenancy/

database/
├── factories/
├── migrations/
└── seeders/

docker/
├── entrypoint.sh
├── nginx/
└── postgres/

docs/
├── API.md
├── ARCHITECTURE.md
├── DATABASE.md
├── POSTMAN.md
└── PROGRESS.md

routes/
└── api.php

tests/
├── Feature/
└── Unit/
```

The project deliberately avoids unnecessary abstraction. A repository layer is used where the customer query/cache boundary benefits from it; simple resources remain service/model driven.

---

## 5. Multi-Tenancy

The application uses a shared PostgreSQL database.

Every tenant-owned record is associated with a `company_id`.

The active tenant is represented by:

```text
TenantContext
```

The tenant is established from the authenticated user's company:

```text
Bearer Token
    ↓
Sanctum User
    ↓
SetTenantContext
    ↓
TenantContext(company_id)
    ↓
TenantScope
    ↓
Tenant-owned query
```

### Tenant isolation rules

`company_id`:

- is never accepted from the request body
- is never accepted from query parameters
- is never accepted from a route parameter as the source of tenancy
- is not client-controlled
- is populated from the active tenant context

Tenant-owned models use the global tenant scope.

Therefore:

```php
Customer::query()->get();
```

automatically operates inside the active tenant.

If a tenant context is missing, the application fails closed instead of returning cross-tenant data.

### Route model binding

Tenant-scoped model binding means a resource belonging to another company is not exposed through its identifier.

The expected behaviour is a `404`, avoiding an existence leak across tenants.

### Jobs

Queued jobs carry the tenant/company identifier explicitly.

A job establishes its tenant context before accessing tenant-owned data:

```php
$tenantContext->runAs(
    $this->companyId,
    function (): void {
        // tenant-aware work
    },
);
```

### Cache

Tenant cache keys are namespaced:

```text
tenant:{company_id}:...
```

This prevents one company's cached data from being reused for another company.

---

## 6. Authentication

Authentication uses Laravel Sanctum personal access tokens.

Main authentication endpoints:

```text
POST /api/v1/auth/register-company
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

Company registration creates:

```text
Company
   +
Owner User
   +
Free Subscription
```

inside a database transaction.

The registration flow also dispatches the welcome-email job after the transaction succeeds.

---

## 7. Roles and Authorization

The application uses three roles:

```text
owner
admin
user
```

Authorization is implemented through Laravel Policies.

High-level permissions:

| Operation            | Owner |      Admin |                    User |
| -------------------- | ----: | ---------: | ----------------------: |
| View company         |   Yes |        Yes |                     Yes |
| Update company       |   Yes |         No |                      No |
| View subscription    |   Yes |        Yes |   Usage-oriented access |
| Upgrade subscription |   Yes |         No |                      No |
| Manage users         |   Yes | Restricted |                      No |
| List users           |   Yes |        Yes |                     Yes |
| Customer CRUD        |   Yes |        Yes |              Restricted |
| Dashboard            |   Yes |        Yes | Summary-oriented access |

The exact authorization rules are enforced by the corresponding Policy classes and feature tests.

---

## 8. Subscription and Usage Limits

The application has subscription plans and subscription history.

A company has one active subscription at a time.

Plans can define limits such as:

```text
max_users
max_customers
```

A `NULL` limit represents an unlimited resource.

Limit checks are implemented using dedicated strategies:

```text
LimitCheck
├── UserLimitCheck
└── CustomerLimitCheck
```

The `LimitEnforcer` coordinates these checks.

This keeps subscription limits independent from individual controllers.

Usage information is exposed through:

```text
GET /api/v1/subscription/usage
```

---

## 9. Company Management

Company information is available through:

```text
GET /api/v1/company
PUT /api/v1/company
```

The company is always resolved from the authenticated user's tenant context.

Clients do not choose the `company_id`.

---

## 10. Users

Users are managed through the REST resource:

```text
GET    /api/v1/users
POST   /api/v1/users
GET    /api/v1/users/{user}
PUT    /api/v1/users/{user}
DELETE /api/v1/users/{user}
```

User management includes:

- tenant isolation
- role-based authorization
- validation
- pagination
- subscription user limits
- invitation job dispatching

---

## 11. Customers

Customers are managed through:

```text
GET    /api/v1/customers
POST   /api/v1/customers
GET    /api/v1/customers/{customer}
PUT    /api/v1/customers/{customer}
DELETE /api/v1/customers/{customer}
```

List filtering supports the customer filter object used by the service/repository layer.

Typical filters include:

```text
?search=
?status=
?sort=
?per_page=
?page=
```

Customer queries are tenant-scoped and paginated.

Customer caching is implemented through the cached repository decorator.

---

## 12. Dashboard

The dashboard endpoint is:

```text
GET /api/v1/dashboard
```

The dashboard is tenant-aware and exposes analytics derived from the company's own data.

Dashboard/cache rebuilds use a tenant-scoped Redis lock to prevent concurrent cache rebuilds.

---

## 13. Redis and Caching

Redis is used for:

- application caching
- rate limiting
- queued jobs

Tenant cache keys use a versioned tenant namespace.

Write operations invalidate the appropriate tenant cache through domain events/listeners.

The invalidation flow is conceptually:

```text
Resource changed
      ↓
TenantResourceChanged
      ↓
InvalidateTenantCache
      ↓
TenantContext::runAs(company_id)
      ↓
Invalidate relevant cache namespace
```

This keeps cache invalidation separate from the resource service itself.

---

## 14. Background Jobs

The project uses queued jobs for work that does not need to block the HTTP response.

Current job responsibilities include:

```text
SendWelcomeEmail
SendUserInvitation
NotifyLimitThreshold
WarmTenantCache
```

Jobs are tenant-aware.

Retry configuration is explicitly defined for retryable jobs.

The job tests verify:

- dispatch behaviour
- tenant context
- retry configuration
- usage threshold behaviour
- cache warming behaviour

---

## 15. Rate Limiting

The application defines named Laravel rate limiters.

### Authentication

Authentication requests are limited per IP/email combination.

```text
5 requests / minute
```

The email component is normalized before generating the limiter key.

### API

Authenticated API requests are limited per user.

```text
60 requests / minute
```

Unauthenticated requests use the client IP as the limiter key.

### Exports

A dedicated export limiter is defined for future export endpoints:

```text
10 requests / minute
```

No export endpoint is introduced solely to exercise the limiter.

---

## 16. Query Safety

Development and test environments prevent accidental lazy loading.

This is intended to expose N+1 query problems early.

The project also uses:

- tenant-leading indexes
- selective eager loading
- pagination
- database constraints
- appropriate unique indexes
- query-count tests where appropriate

PostgreSQL schema/index decisions are documented in:

```text
docs/DATABASE.md
```

---

## 17. API Documentation

The complete API reference is available at:

```text
docs/API.md
```

The Postman collection is available at:

```text
docs/postman/multi-tenant-saas.json
```

Base URL for local development:

```text
http://localhost:8000/api/v1
```

The API uses JSON responses.

Authenticated endpoints require:

```http
Authorization: Bearer <token>
Accept: application/json
```

---

## 18. Docker Setup

### Prerequisites

Install:

- Docker
- Docker Compose
- Git

The application is intended to run through Docker rather than requiring a host PHP/PostgreSQL/Redis installation.

### First run

```bash
docker compose up --build
```

The application bootstrap process:

1. creates `.env` from `.env.example` if necessary
2. waits for PostgreSQL and Redis
3. installs Composer dependencies when required
4. generates `APP_KEY` when missing
5. runs migrations
6. seeds plans when the plans table is empty
7. marks the application ready
8. allows queue/scheduler services to start

### Subsequent runs

```bash
docker compose up
```

### Services

| Service     | Purpose                        |
| ----------- | ------------------------------ |
| `app`       | PHP/Laravel application        |
| `nginx`     | HTTP entry point               |
| `postgres`  | PostgreSQL database            |
| `redis`     | Cache, queue and rate limiting |
| `queue`     | Laravel queue worker           |
| `scheduler` | Laravel scheduler              |

Local HTTP entry point:

```text
http://localhost:8000
```

---

## 19. Environment Configuration

Start from:

```bash
cp .env.example .env
```

When using the provided Docker bootstrap, this step is handled automatically when `.env` does not exist.

Important environment areas include:

```text
APP_ENV
APP_KEY
APP_URL

DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

REDIS_HOST
REDIS_PORT

CACHE_STORE
QUEUE_CONNECTION
```

Do not commit real credentials or secrets.

---

## 20. Makefile Commands

Useful shortcuts:

```bash
make up
make down
make test
make fresh
make logs
```

Run Artisan:

```bash
make artisan c="route:list"
```

Run Composer:

```bash
make composer c="require vendor/package"
```

The exact Makefile remains the source of truth for available shortcuts.

---

## 21. Testing

Run the complete test suite:

```bash
make test
```

Equivalent command:

```bash
docker compose exec app php artisan test
```

The test environment uses the dedicated testing database.

The suite covers areas including:

- authentication
- authorization
- tenant isolation
- company management
- subscription behaviour
- usage limits
- user CRUD
- customer CRUD
- dashboard
- cache behaviour
- cache invalidation
- background jobs
- rate limiting
- lazy-loading protection
- query behaviour

Tests must not be removed simply to make the suite pass.

When a test fails, the underlying implementation or test fixture should be corrected.

---

## 22. Static Analysis

Run Larastan/PHPStan using the project's configured command.

Typical command:

```bash
docker compose exec app vendor/bin/phpstan analyse
```

Static analysis should pass without introducing new ignored errors.

New PHP classes and functions should contain useful PHPDoc where required by the project's static-analysis configuration.

---

## 23. Code Formatting

Run Laravel Pint:

```bash
docker compose exec app vendor/bin/pint
```

Before committing:

```bash
docker compose exec app vendor/bin/pint --test
```

Formatting changes should remain limited to the intended project changes.

---

## 24. Package Management

Add a PHP dependency:

```bash
docker compose exec app composer require vendor/package
```

Add a development dependency:

```bash
docker compose exec app composer require --dev vendor/package
```

Commit both:

```text
composer.json
composer.lock
```

For system packages or PHP extensions, update the Dockerfile and rebuild:

```bash
docker compose up --build
```

---

## 25. Database

PostgreSQL is the system of record.

The schema uses:

- foreign keys
- unique constraints
- partial indexes
- functional indexes where appropriate
- tenant-leading indexes
- soft-delete-aware indexes for customers

Important tenancy rule:

```text
company_id must be the leading column of tenant-specific indexes
```

The complete schema and index rationale are documented in:

```text
docs/DATABASE.md
```

---

## 26. Security Principles

The project follows these security rules:

### Tenant isolation

Never trust a client-provided `company_id`.

### Authorization

Do not rely on authentication alone. Resource access is checked through Policies.

### Validation

Input is validated through Form Requests.

### Passwords

Passwords are handled through Laravel's password hashing facilities.

### API authentication

Sanctum personal access tokens are used for API authentication.

### Rate limiting

Authentication and API traffic are rate limited.

### Secrets

Secrets belong in environment configuration and must not be committed.

### SQL safety

Application queries use Laravel's query builder/Eloquent rather than interpolating untrusted SQL.

### Data integrity

Important business invariants are also protected at the database level.

---

## 27. Production Considerations

The Docker setup is primarily intended for development and project evaluation.

Before production deployment, verify:

- production `.env` values
- secret management
- HTTPS
- database backups
- backup restoration
- Redis persistence/availability requirements
- queue worker supervision
- scheduler supervision
- log aggregation
- error monitoring
- database connection limits
- PHP-FPM/Nginx configuration
- cache configuration
- trusted proxy configuration
- CORS policy
- token expiration/revocation policy
- infrastructure health checks

The repository's production Docker target is documented separately from the development bootstrap.

---

## 28. Development Workflow

The project follows a small-step delivery process.

Each roadmap step is intended to produce:

1. only the code for that step
2. tests for that step
3. relevant documentation
4. an updated progress record
5. one logical commit

The project roadmap contains 17 logical commits.

The final development step is:

```text
Step 16 — README + final verification
```

Commit:

```text
docs: add README and final submission checklist
```

---

## 29. Project Documentation

| Document                              | Purpose                          |
| ------------------------------------- | -------------------------------- |
| `docs/API.md`                         | API reference                    |
| `docs/ARCHITECTURE.md`                | Architecture and design patterns |
| `docs/DATABASE.md`                    | Schema, constraints and indexes  |
| `docs/PROGRESS.md`                    | Implementation progress          |
| `docs/postman/multi-tenant-saas.json` | Postman API collection           |

These documents are intentionally kept separate so that implementation, architecture and API concerns remain easy to review.

---

## 30. Final Verification

Before considering the project complete, perform:

```bash
docker compose up --build
```

Then verify:

```bash
docker compose ps
```

Run:

```bash
make test
```

Run static analysis:

```bash
docker compose exec app vendor/bin/phpstan analyse
```

Run formatting verification:

```bash
docker compose exec app vendor/bin/pint --test
```

Verify routes:

```bash
make artisan c="route:list"
```

Verify the health endpoint:

```text
GET /api/v1/health
```

Verify API authentication:

```text
register → login → token → authenticated endpoint
```

Verify tenant isolation with two companies.

Verify queue worker and scheduler are running.

Verify Redis connectivity.

Verify the Postman collection imports successfully.

---

## 31. Final Submission Checklist

- [ ] Application boots with Docker
- [ ] PostgreSQL starts successfully
- [ ] Redis starts successfully
- [ ] Queue worker starts successfully
- [ ] Scheduler starts successfully
- [ ] Database migrations run successfully
- [ ] Plans are seeded
- [ ] Health endpoint responds
- [ ] Company registration works
- [ ] Login works
- [ ] Sanctum authentication works
- [ ] Logout revokes the current token
- [ ] Tenant context is established from the authenticated user
- [ ] Cross-tenant access is blocked
- [ ] RBAC policies are enforced
- [ ] Subscription rules work
- [ ] Usage limits work
- [ ] User CRUD works
- [ ] Customer CRUD works
- [ ] Customer filtering/pagination works
- [ ] Dashboard works
- [ ] Redis caching works
- [ ] Cache invalidation works
- [ ] Background jobs dispatch correctly
- [ ] Jobs restore tenant context
- [ ] Rate limiting works
- [ ] Lazy-loading protection works outside production
- [ ] No accidental N+1 queries remain in reviewed paths
- [ ] API documentation is present
- [ ] Postman collection is present
- [ ] Full test suite passes
- [ ] PHPStan passes
- [ ] Pint check passes
- [ ] No test was deleted to make the suite pass
- [ ] No unrelated files were changed
- [ ] `.env` and secrets are not committed
- [ ] `docs/PROGRESS.md` is updated
- [ ] Git diff has been reviewed
- [ ] Final commit message is:

```text
docs: add README and final submission checklist
```

---

## 32. Final Architecture Summary

```text
                    CLIENT
                       |
                       v
                Nginx / Laravel
                       |
                       v
               Sanctum Authentication
                       |
                       v
                SetTenantContext
                       |
                       v
                 Controller
                       |
          +------------+------------+
          |                         |
          v                         v
     FormRequest                 Policy
          |                         |
          +------------+------------+
                       |
                       v
                    Service
                       |
       +---------------+---------------+
       |               |               |
       v               v               v
 Repository          Limits          Cache
       |               |               |
       +---------------+---------------+
                       |
                       v
                  PostgreSQL
                       |
          +------------+------------+
          |                         |
          v                         v
        Redis                    Queue Jobs
          |                         |
          +------------+------------+
                       |
                       v
                 TenantContext
```

The central architectural invariant is:

```text
Authenticated User
       ↓
company_id
       ↓
TenantContext
       ↓
TenantScope / BelongsToTenant
       ↓
Tenant-owned data
```

The client never chooses the tenant.

---

## 33. Status

This README describes the intended final architecture and operational workflow of the project.

For implementation history and the exact completed roadmap steps, see:

```text
docs/PROGRESS.md
```
