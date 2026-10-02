# ARCHITECTURE

Companion to `PLAN.md`. This file answers: _how is the code organised, which patterns are used, and why._

## 1. Principles

1. Tenant isolation is enforced **below** application code (global scope + fail-closed context), so a forgotten `where` cannot leak data.
2. Business rules live in **services**, never in controllers, models or FormRequests.
3. Abstraction only where it pays: repository for Customer only, interfaces only where there are 2 implementations or a real test seam.
4. Every rule has a stable error code and a test.

## 2. Layers

```
HTTP request
  -> Middleware: auth:sanctum -> SetTenantContext -> throttle
  -> FormRequest        (shape/format validation, tenant-scoped exists/unique)
  -> Policy (authorize) (who may do this to this resource)
  -> Controller         (thin: call one service method, return Resource)
  -> Service            (business rules, transactions, limits, domain exceptions)
  -> Repository|Eloquent (queries; tenant scope applied automatically)
  -> Resource           (JSON shape)
  -> Response envelope  {success, message, data, meta?}
```

Dependency direction is one way: Controller -> Service -> Repository/Model. Services never read `Request`; they receive plain data/DTOs and throw domain exceptions.

## 3. Folder structure

```
app/
  Enums/            Role, UserStatus, CustomerStatus, SubscriptionStatus
  Exceptions/       BusinessRuleException (base) + one class per business rule, Handler wiring
  Http/
    Controllers/Api/V1/   AuthController, CompanyController, PlanController, SubscriptionController,
                          UserController, CustomerController, DashboardController
    Middleware/            SetTenantContext, ForceJsonResponse
    Requests/              one FormRequest per write action (+ list/filter requests)
    Resources/             one Resource per model (+ collections via meta)
  Models/           Company, User, Customer, Plan, Subscription
  Observers/        UserObserver, CustomerObserver, SubscriptionObserver
  Events/ Listeners/ Jobs/
  Policies/         CompanyPolicy, UserPolicy, CustomerPolicy, SubscriptionPolicy
  Repositories/     Contracts/CustomerRepositoryInterface, EloquentCustomerRepository, CachedCustomerRepository
  Services/         AuthService, CompanyRegistrationService, CompanyService, UserService, CustomerService,
                    SubscriptionService, UsageService, DashboardService
    Limits/         LimitCheck (interface), UserLimitCheck, CustomerLimitCheck, LimitEnforcer
  Support/
    Tenancy/        TenantContext, TenantScope, BelongsToTenant
    Cache/          TenantCache (keys, TTLs, version bump)
    ApiResponse.php envelope helper
database/ migrations, factories, seeders
tests/ Feature/ (per resource), Unit/ (limits, tenancy, cache keys)
docker/ entrypoint.sh, nginx conf, postgres init.sh
docs/
```

Routes: `routes/api.php`, versioned group `/api/v1`.

## 4. Tenancy flow (fail closed)

```
Bearer token -> Sanctum resolves User -> SetTenantContext sets TenantContext(company_id = user.company_id)
Model::query() -> TenantScope adds  WHERE company_id = TenantContext::id()
Model::create() -> BelongsToTenant fills company_id from TenantContext
```

- `company_id` is **never** read from request body, query or route. It is not in `$fillable`.
- If a tenant model is queried with no context set, `TenantScope` throws `TenantContextMissingException` (fail closed) instead of returning all rows. Console/seeders/tests use `TenantContext::runAs($companyId, fn)` or `Model::withoutTenancy()` explicitly.
- Route model binding goes through the scope: another tenant's id -> **404**, not 403 (no existence leak).
- `Rule::exists/unique` in FormRequests are always constrained by `company_id`.
- Jobs receive `company_id` and call `TenantContext::runAs(...)` in `handle()`.

## 5. Patterns used (and why)

| Pattern                   | Where                                                                                                           | Why                                                                                                                                                      | What we avoided                                                                                               |
| ------------------------- | --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Global Scope + Trait      | `TenantScope`, `BelongsToTenant`                                                                                | Isolation cannot be forgotten per query                                                                                                                  | Manual `where company_id` in every query; a tenancy package (hides the mechanism the reviewer will ask about) |
| Service layer             | `app/Services`                                                                                                  | Business rules and transactions in one testable place; thin controllers                                                                                  | Fat controllers / fat models                                                                                  |
| Strategy                  | `LimitCheck` impls                                                                                              | Add a new limited resource without editing `LimitEnforcer` (Open/Closed). The same checks feed enforcement, usage API and dashboard: one source of truth | `if ($resource === 'users')` chains                                                                           |
| Repository                | **Customer only**                                                                                               | Heavy filter/search/sort/pagination logic + a seam to add caching                                                                                        | Repositories for every model (just wrappers around Eloquent)                                                  |
| Decorator                 | `CachedCustomerRepository` wraps `EloquentCustomerRepository`                                                   | Caching added without touching query code; swap by container binding (step 12)                                                                           | Cache calls scattered through services                                                                        |
| Observer + Event/Listener | Observers dispatch `TenantResourceChanged(companyId, resource)`; `InvalidateTenantCache` listens (after commit) | Invalidation is automatic and centralised; services don't remember to clear cache                                                                        | Manual `Cache::forget` after each write                                                                       |
| Policy                    | `app/Policies`                                                                                                  | Idiomatic Laravel RBAC; one place per resource for the role matrix                                                                                       | Role `if`s in controllers                                                                                     |
| Form Request              | `app/Http/Requests`                                                                                             | Validation separated from controller                                                                                                                     | Inline `$request->validate()`                                                                                 |
| API Resource              | `app/Http/Resources`                                                                                            | Stable response shape, never leak `password`/internal columns                                                                                            | `->toArray()` on models                                                                                       |
| Enum                      | `Role`, statuses                                                                                                | No magic strings; DB CHECK constraints mirror them                                                                                                       | String constants                                                                                              |
| Domain exceptions         | `BusinessRuleException` subclasses                                                                              | Services signal rule violations; one handler maps to `{code, status}`                                                                                    | Returning arrays/booleans for failures                                                                        |
| DTO (readonly class)      | `CustomerFilters` only                                                                                          | Filter set is used by repository and by the cache key; a typed object avoids array key typos                                                             | DTOs for every request                                                                                        |

Deliberately **not** used: DDD folder layers, CQRS, event sourcing, generic repository, service interfaces without a second implementation.

## 6. SOLID mapping (concrete)

- **S**: `UserService` manages users; `UsageService` computes usage; `LimitEnforcer` enforces limits; `TenantCache` knows keys/TTLs. None does two jobs.
- **O**: new limited resource = new `LimitCheck` class + tag; enforcer, usage and dashboard unchanged.
- **L**: `CachedCustomerRepository` and `EloquentCustomerRepository` are interchangeable behind `CustomerRepositoryInterface` (same contract, same results).
- **I**: `LimitCheck` is a 3-method interface; `CustomerRepositoryInterface` exposes only what `CustomerService` needs.
- **D**: services depend on `CustomerRepositoryInterface` and `LimitCheck`, bound in a service provider; services get `TenantContext` and `TenantCache` injected, not static state.

## 7. Limit enforcement design

```php
interface LimitCheck {
    public function resource(): string;                 // 'users' | 'customers'
    public function used(int $companyId): int;          // authoritative COUNT(*)
    public function limitFor(Plan $plan): ?int;         // null = unlimited
}
final class LimitEnforcer {
    /** @param iterable<LimitCheck> $checks (tagged) */
    public function ensureCanCreate(Company $company, string $resource): void; // throws PlanLimitReachedException
}
```

Flow (inside the service's `DB::transaction`): lock the company's active subscription row (`lockForUpdate`) -> `used()` -> compare with `limitFor(plan)` -> insert. Two concurrent creates serialise on the lock, so the limit cannot be exceeded.
`UsageService` iterates the same tagged checks to build `{resource: {used, limit, percent}}`.

## 8. Errors

`BusinessRuleException { string $errorCode; int $httpStatus; array $details }`. The exception handler renders every error in one envelope:

```json
{
    "success": false,
    "message": "...",
    "error": { "code": "PLAN_LIMIT_REACHED", "details": {} }
}
```

Mapping: validation -> 422 `VALIDATION_ERROR`; unauthenticated -> 401; policy denied -> 403 `FORBIDDEN`; model not found -> 404; throttled -> 429 `RATE_LIMITED`; domain rules per `PLAN.md` 5a; anything else -> 500 with generic message (no trace in prod).

## 9. Transactions & concurrency

- Services own transactions; controllers never.
- Company registration, plan upgrade and any limited create are single transactions.
- Mass updates/deletes that bypass Eloquent events are forbidden for tenant data (they skip observers, so cache would go stale). Use model instances, or dispatch `TenantResourceChanged` manually.

## 10. Caching architecture (detail in `PLAN.md` section 7)

`TenantCache` is the only class that builds keys: `tenant:{id}:{name}[:v{n}:{hash}]`. Reads: cache-aside via `TenantCache::remember(key, ttl, callback)`. Writes: observers -> event -> listener (after DB commit) forgets keys or bumps the customers list version. Dashboard rebuild is guarded by an atomic lock (stampede control).

## 11. Background jobs

Jobs are queued on Redis, carry `company_id` (not models), set tenant context in `handle()`, have `tries` + `backoff`, and failures land in `failed_jobs`. Chosen jobs and reasons are in `PLAN.md` section 8 and will be repeated in the README.

## 12. Testing architecture

- `Tests\TestCase` helpers: `createTenant(planSlug)`, `actingAsRole(Role, ?Company)`, `assertErrorCode()`.
- Feature tests hit the HTTP API (auth, policies, validation, envelope). Unit tests cover `LimitEnforcer`, `TenantScope`, `TenantCache`.
- Tenant isolation tests always create **two** tenants and assert 404/empty results across them.
- Test DB `app_testing` (Postgres, same engine as prod so partial unique indexes behave identically); Redis DB index 1.

## 13. Reviewer cheat-sheet (answers you must be able to give)

| Question                                                | Short answer                                                                                                                                                   |
| ------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Why shared DB + `company_id`?                           | Cheapest to run/migrate at many tenants; isolation enforced by scope + tests. DB-per-tenant is stronger isolation but costly to operate; trade-off documented. |
| How is isolation guaranteed?                            | Global scope, fail-closed context from the authenticated user, `company_id` never from input, 404 on foreign ids, isolation tests with two tenants.            |
| Why a repository only for Customer?                     | It has real query complexity and a caching seam; others are simple Eloquent used in services.                                                                  |
| Why Strategy for limits?                                | Open/Closed; one source of truth for enforce + usage + dashboard.                                                                                              |
| Why can't a tenant downgrade?                           | Data created under a higher plan can't be held by a lower limit; rule `DOWNGRADE_NOT_ALLOWED`.                                                                 |
| How do you avoid exceeding the limit under concurrency? | Transaction + `lockForUpdate` on the subscription row, then authoritative count.                                                                               |
| When is cache invalidated?                              | On every write via observers -> event -> listener after commit; table in `PLAN.md` 7.                                                                          |
