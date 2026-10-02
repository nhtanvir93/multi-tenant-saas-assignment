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

---

## Authentication

Authentication uses Laravel Sanctum personal access tokens.

Authenticated endpoints require:

```http
Authorization: Bearer {token}
Accept: application/json
```

### `POST /auth/register-company` (public)

Creates a new company, its owner user, and an active subscription to the `free` plan in one database transaction.

Request:

```bash
curl -X POST http://localhost:8000/api/v1/auth/register-company \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "company_name": "Acme Ltd",
    "company_slug": "acme-ltd",
    "name": "Acme Owner",
    "email": "owner@acme.test",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

Validation:

- `company_name`: required, string, maximum 150 characters.
- `company_slug`: required, string, maximum 150 characters, alpha-dash, unique.
- `name`: required, string, maximum 150 characters.
- `email`: required, valid email, maximum 255 characters.
- `password`: required, confirmed, minimum 8 characters.
- `company_slug` and `email` are normalised to lowercase.

201:

```json
{
    "success": true,
    "message": "Company registered successfully.",
    "data": {
        "company": {
            "id": 1,
            "name": "Acme Ltd",
            "slug": "acme-ltd"
        },
        "user": {
            "id": 1,
            "name": "Acme Owner",
            "email": "owner@acme.test",
            "role": "owner",
            "status": "active"
        },
        "subscription": {
            "id": 1,
            "plan": {
                "id": 1,
                "slug": "free",
                "name": "Free",
                "tier": 1,
                "price_cents": 0,
                "limits": {
                    "users": 5,
                    "customers": 100
                },
                "features": {
                    "api_access": true,
                    "csv_export": false,
                    "priority_support": false
                }
            },
            "status": "active",
            "starts_at": "2026-10-02T00:00:00.000000Z",
            "ends_at": null
        },
        "token": "1|..."
    }
}
```

If registration fails during the transaction, the company, owner and subscription are rolled back together.

Errors:

- `422 VALIDATION_ERROR` for invalid input.
- `500 INTERNAL_ERROR` if the active `free` plan is not configured or an unexpected error occurs.

### `POST /auth/login` (public)

Authenticates an active user by email and password and returns a Sanctum token.

Request:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "owner@acme.test",
    "password": "password123"
  }'
```

Validation:

- `email`: required, valid email.
- `password`: required, string.
- Email is normalised to lowercase.

200:

```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "user": {
            "id": 1,
            "name": "Acme Owner",
            "email": "owner@acme.test",
            "role": "owner",
            "status": "active"
        },
        "token": "2|..."
    }
}
```

Invalid credentials, inactive user, or incorrect password:

```json
{
    "success": false,
    "message": "Credentials mismatched",
    "error": {
        "code": "INVALID_CREDENTIALS",
        "details": {}
    }
}
```

HTTP status: `401`.

The login lookup is performed without an active tenant context because tenant context does not exist before authentication.

### `POST /auth/logout` (authenticated)

Revokes the currently authenticated Sanctum access token.

```bash
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}"
```

200:

```json
{
    "success": true,
    "message": "Logged out successfully.",
    "data": null
}
```

Errors:

- `401 UNAUTHENTICATED` when the token is missing or invalid.

### `GET /auth/me` (authenticated)

Returns the currently authenticated user and company.

```bash
curl http://localhost:8000/api/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}"
```

200:

```json
{
    "success": true,
    "message": "Authenticated user.",
    "data": {
        "id": 1,
        "name": "Acme Owner",
        "email": "owner@acme.test",
        "role": "owner",
        "status": "active",
        "company": {
            "id": 1,
            "name": "Acme Ltd",
            "slug": "acme-ltd"
        }
    }
}
```

Errors:

- `401 UNAUTHENTICATED` when the token is missing or invalid.

### Tenant/authentication ordering

Sanctum resolves the token's `tokenable` user before the tenant-context middleware runs. The token-user lookup therefore bypasses the tenant scope.

For normal authenticated application requests, `SetTenantContext` establishes the company context before tenant-scoped resources are resolved.

This preserves the Step 5 rule that tenant-scoped models cannot be queried without a tenant context while still allowing authentication to establish that context.

---

## Future endpoints

Additional endpoint documentation is added in the step that implements the corresponding feature. Step 15 finalises this document and adds the Postman collection.
