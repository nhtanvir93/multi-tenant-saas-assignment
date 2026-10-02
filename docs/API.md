# API reference

Grows with every step; finalised (with a Postman collection) in step 15.
Base URL: `http://localhost:8000/api/v1`. All requests and responses are JSON.

## Conventions

Success:

```json
{ "success": true, "message": "Plans retrieved.", "data": {}, "meta": {} }
```

`meta` appears only on paginated lists.

Error:

```json
{
    "success": false,
    "message": "Resource not found.",
    "error": { "code": "NOT_FOUND", "details": {} }
}
```

`details` appears only when there is extra information (validation fields, limit info).

| HTTP | `error.code`          | Meaning                                                 |
| ---- | --------------------- | ------------------------------------------------------- |
| 401  | `UNAUTHENTICATED`     | missing/invalid token                                   |
| 403  | `FORBIDDEN`           | authenticated but not allowed                           |
| 404  | `NOT_FOUND`           | unknown route/resource (also another tenant's resource) |
| 405  | `METHOD_NOT_ALLOWED`  | wrong HTTP method                                       |
| 422  | `VALIDATION_ERROR`    | invalid input, `details` = field errors                 |
| 429  | `RATE_LIMITED`        | too many requests                                       |
| 500  | `INTERNAL_ERROR`      | unexpected error, no internals leaked                   |
| 503  | `SERVICE_UNAVAILABLE` | health check failed                                     |

Business-rule codes (`PLAN_LIMIT_REACHED`, `DOWNGRADE_NOT_ALLOWED`, ...) are listed in `docs/PLAN.md` section 5a and documented with the endpoints that raise them.

---

## Health

### `GET /health` (public)

```bash
curl http://localhost:8000/api/v1/health
```

200:

```json
{
    "success": true,
    "message": "Healthy",
    "data": { "status": "ok", "checks": { "database": "up", "redis": "up" } }
}
```

503 (a dependency is down):

```json
{
    "success": false,
    "message": "Service degraded.",
    "error": {
        "code": "SERVICE_UNAVAILABLE",
        "details": { "checks": { "database": "up", "redis": "down" } }
    }
}
```

## Plans

### `GET /plans` (public: global reference data, needed before registration)

Returns active plans ordered by `tier` (upgrade order). `limits.*: null` means unlimited. `price_cents` is in the smallest currency unit (no payment gateway in this project, see assumptions).

```bash
curl http://localhost:8000/api/v1/plans
```

200:

```json
{
    "success": true,
    "message": "Plans retrieved.",
    "data": [
        {
            "id": 1,
            "slug": "free",
            "name": "Free",
            "tier": 1,
            "price_cents": 0,
            "limits": { "users": 5, "customers": 100 },
            "features": {
                "api_access": true,
                "csv_export": false,
                "priority_support": false
            }
        },
        {
            "id": 2,
            "slug": "pro",
            "name": "Pro",
            "tier": 2,
            "price_cents": 4900,
            "limits": { "users": 50, "customers": 5000 },
            "features": {
                "api_access": true,
                "csv_export": true,
                "priority_support": false
            }
        },
        {
            "id": 3,
            "slug": "enterprise",
            "name": "Enterprise",
            "tier": 3,
            "price_cents": 19900,
            "limits": { "users": null, "customers": null },
            "features": {
                "api_access": true,
                "csv_export": true,
                "priority_support": true
            }
        }
    ]
}
```

Errors: 405 if a method other than GET is used.
