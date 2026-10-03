# Multi-Tenant SaaS API Reference

## Table of Contents

- [Base URL](#base-url)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Response Convention](#response-convention)
- [1. Health](#1-health)
- [2. Plans](#2-plans)
- [3. Authentication Endpoints](#3-authentication-endpoints)
- [4. Company](#4-company)
- [5. Subscription](#5-subscription)
- [6. Users](#6-users)
- [7. Customers](#7-customers)
- [8. Dashboard](#8-dashboard)
- [Tenant Context](#tenant-context)
- [Tenant-Scoped Resources](#tenant-scoped-resources)
- [Complete Route Summary](#complete-route-summary)

---

## Base URL

```text
/api/v1
```

Local development:

```text
http://localhost/api/v1
```

---

## Authentication

Authenticated API requests use **Laravel Sanctum** bearer tokens.

```http
Authorization: Bearer <token>
Accept: application/json
```

The authenticated user's `company_id` determines the active tenant.

> **Important:** Tenant-scoped endpoints must not accept a client-supplied `company_id` to switch tenants.

---

## Rate Limiting

### Authentication Rate Limit

The following endpoints use the `auth` rate limiter:

- `POST /auth/register-company`
- `POST /auth/login`

**Limit:** 5 requests/minute

The rate-limit key is based on: `IP address + normalized email`

### API Rate Limit

Authenticated API routes use the `api` rate limiter.

**Limit:** 60 requests/minute

- Authenticated requests are limited by **user identity**.
- Unauthenticated requests are limited by **IP address**.

---

## Response Convention

Successful responses use the project's API response wrapper.

Validation and authorization failures use the HTTP status codes and error structure produced by the application.

> For exact response payloads, the current Controller, Resource, Form Request, and API response implementation are the source of truth.

---

## 1. Health

### `GET /health`

Checks application health.

| Property       | Value        |
| -------------- | ------------ |
| Authentication | Not required |
| Request body   | None         |

**Example**

```http
GET /api/v1/health
Accept: application/json
```

---

## 2. Plans

### `GET /plans`

Returns the available subscription plans.

| Property       | Value        |
| -------------- | ------------ |
| Authentication | Not required |
| Request body   | None         |

**Example**

```http
GET /api/v1/plans
Accept: application/json
```

---

## 3. Authentication Endpoints

### `POST /auth/register-company`

Creates a new company, owner user, and initial subscription.

| Property       | Value             |
| -------------- | ----------------- |
| Authentication | Not required      |
| Rate limit     | 5 requests/minute |

**Request**

```json
{
    "company_name": "Acme Inc.",
    "name": "Owner",
    "email": "owner@example.com",
    "password": "password123"
}
```

**Validation**

The registration Form Request validates:

- company name
- owner name
- email
- password

The exact validation rules are defined by the application's registration request class.

**Transaction**

Company registration is performed inside a database transaction. The company, owner user, and initial subscription are created atomically.

**Background Job**

After successful transaction completion, the welcome email job is dispatched.

---

### `POST /auth/login`

Authenticates an existing user and creates a Sanctum token.

| Property       | Value             |
| -------------- | ----------------- |
| Authentication | Not required      |
| Rate limit     | 5 requests/minute |

**Request**

```json
{
    "email": "owner@example.com",
    "password": "password123"
}
```

**Validation**

The login Form Request validates the submitted credentials.

---

### `GET /auth/me`

Returns the currently authenticated user, together with the company relationship.

| Property       | Value    |
| -------------- | -------- |
| Authentication | Required |

**Example**

```http
GET /api/v1/auth/me
Authorization: Bearer <token>
Accept: application/json
```

---

### `POST /auth/logout`

Logs out the current authenticated user.

| Property       | Value    |
| -------------- | -------- |
| Authentication | Required |

**Example**

```http
POST /api/v1/auth/logout
Authorization: Bearer <token>
Accept: application/json
```

---

## 4. Company

### `GET /company`

Returns the authenticated user's company.

| Property       | Value    |
| -------------- | -------- |
| Authentication | Required |

**Tenant Isolation**

The company is resolved from the authenticated user's tenant context.

**Example**

```http
GET /api/v1/company
Authorization: Bearer <token>
Accept: application/json
```

---

### `PUT /company`

Updates the authenticated user's company.

| Property       | Value                                        |
| -------------- | -------------------------------------------- |
| Authentication | Required                                     |
| Authorization  | Enforced by the application's company policy |

**Request**

```json
{
    "name": "Updated Company Name"
}
```

**Validation**

The company update Form Request defines the accepted fields and validation rules.

**Example**

```http
PUT /api/v1/company
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json
```

---

## 5. Subscription

### `GET /subscription`

Returns the current tenant's subscription.

| Property       | Value    |
| -------------- | -------- |
| Authentication | Required |

**Example**

```http
GET /api/v1/subscription
Authorization: Bearer <token>
Accept: application/json
```

---

### `PUT /subscription`

Changes the current tenant's subscription plan.

| Property       | Value                                              |
| -------------- | -------------------------------------------------- |
| Authentication | Required                                           |
| Authorization  | Protected by the application's authorization rules |

**Request**

```json
{
    "plan_id": 2
}
```

**Validation**

`plan_id` must reference a valid subscription plan according to the application's subscription update request.

**Transaction**

The subscription update is performed inside a database transaction.

**Background Job**

After a successful subscription change, the tenant cache warming job is dispatched.

---

### `GET /subscription/usage`

Returns usage information for the current tenant.

| Property       | Value    |
| -------------- | -------- |
| Authentication | Required |

**Tenant Isolation**

Usage is calculated for the active tenant. The endpoint uses the application's usage and caching services.

**Example**

```http
GET /api/v1/subscription/usage
Authorization: Bearer <token>
Accept: application/json
```

---

## 6. Users

Users are **tenant-scoped**. All user endpoints require authentication.

### `GET /users`

Returns a paginated list of users belonging to the current tenant.

**Tenant Isolation:** Only users belonging to the active tenant can be returned.

**Example**

```http
GET /api/v1/users
Authorization: Bearer <token>
Accept: application/json
```

---

### `POST /users`

Creates a user inside the current tenant.

| Property       | Value                                  |
| -------------- | -------------------------------------- |
| Authentication | Required                               |
| Authorization  | Application's user policy / RBAC rules |

**Request**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "role": "user"
}
```

**Validation**

The user creation Form Request validates the submitted fields.

The user's tenant is obtained from the active `TenantContext`.

> The client must not provide an arbitrary `company_id` to create a user in another tenant.

**Subscription Limit**

User creation passes through the subscription limit enforcement layer. If the tenant has reached its user limit, creation is rejected.

**Background Job**

After successful creation, the user invitation job is dispatched.

---

### `GET /users/{user}`

Returns a user belonging to the current tenant.

| Property       | Value                     |
| -------------- | ------------------------- |
| Authentication | Required                  |
| Authorization  | Application's user policy |

**Tenant Isolation:** The requested user must belong to the active tenant.

**Example**

```http
GET /api/v1/users/10
Authorization: Bearer <token>
Accept: application/json
```

---

### `PUT /users/{user}`

Updates a user belonging to the current tenant.

| Property       | Value                                  |
| -------------- | -------------------------------------- |
| Authentication | Required                               |
| Authorization  | Application's user policy / RBAC rules |

**Request**

```json
{
    "name": "Updated User"
}
```

The exact accepted fields and validation rules are defined by the application's user update Form Request.

**Tenant Isolation:** The target user must belong to the active tenant.

---

### `DELETE /users/{user}`

Deletes a user belonging to the current tenant.

| Property       | Value                                  |
| -------------- | -------------------------------------- |
| Authentication | Required                               |
| Authorization  | Application's user policy / RBAC rules |

**Tenant Isolation:** The target user must belong to the active tenant.

**Example**

```http
DELETE /api/v1/users/10
Authorization: Bearer <token>
Accept: application/json
```

---

## 7. Customers

Customers are **tenant-scoped**. All customer endpoints require authentication.

### `GET /customers`

Returns a paginated list of customers belonging to the current tenant.

**Query Parameters**

| Parameter | Purpose                    |
| --------- | -------------------------- |
| `search`  | Search customer records    |
| `status`  | Filter by customer status  |
| `sort`    | Control sorting            |
| `perPage` | Number of records per page |
| `page`    | Requested page             |

**Tenant Isolation:** Only customers belonging to the active tenant are returned.

**Example**

```http
GET /api/v1/customers?search=acme&status=active&sort=-created_at&perPage=20&page=1
Authorization: Bearer <token>
Accept: application/json
```

---

### `POST /customers`

Creates a customer inside the current tenant.

| Property       | Value                         |
| -------------- | ----------------------------- |
| Authentication | Required                      |
| Authorization  | Application's customer policy |

**Request**

```json
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+8801700000000",
    "status": "active"
}
```

**Validation**

The customer creation Form Request validates the submitted customer fields.

The tenant is obtained from the active `TenantContext`.

> The client must not provide an arbitrary `company_id`.

**Subscription Limit**

Customer creation passes through the subscription limit enforcement layer. If the tenant has reached its customer limit, creation is rejected.

---

### `GET /customers/{customer}`

Returns a customer belonging to the current tenant.

| Property       | Value                         |
| -------------- | ----------------------------- |
| Authentication | Required                      |
| Authorization  | Application's customer policy |

**Tenant Isolation:** The requested customer must belong to the active tenant.

**Example**

```http
GET /api/v1/customers/25
Authorization: Bearer <token>
Accept: application/json
```

---

### `PUT /customers/{customer}`

Updates a customer belonging to the current tenant.

| Property       | Value                         |
| -------------- | ----------------------------- |
| Authentication | Required                      |
| Authorization  | Application's customer policy |

**Request**

```json
{
    "name": "Updated Customer"
}
```

The exact accepted fields and validation rules are defined by the customer update Form Request.

**Tenant Isolation:** The target customer must belong to the active tenant.

---

### `DELETE /customers/{customer}`

Deletes a customer belonging to the current tenant.

| Property       | Value                         |
| -------------- | ----------------------------- |
| Authentication | Required                      |
| Authorization  | Application's customer policy |

**Tenant Isolation:** The target customer must belong to the active tenant.

**Example**

```http
DELETE /api/v1/customers/25
Authorization: Bearer <token>
Accept: application/json
```

---

## 8. Dashboard

### `GET /dashboard`

Returns dashboard analytics for the current tenant.

| Property       | Value    |
| -------------- | -------- |
| Authentication | Required |

**Tenant Isolation:** Dashboard analytics are calculated for the active tenant.

**Caching:** Dashboard data uses the tenant-scoped caching layer.

**Example**

```http
GET /api/v1/dashboard
Authorization: Bearer <token>
Accept: application/json
```

---

## Tenant Context

Authenticated tenant-scoped API requests use the following middleware:

```text
auth:sanctum
throttle:api
SetTenantContext
```

The active tenant is derived from the authenticated user.

The application uses `TenantContext` to hold the active `company_id` during the request or queued job.

Tenant-aware repositories, services, cache keys, and background jobs use this context to maintain tenant isolation.

---

## Tenant-Scoped Resources

| Resource     | Tenant Scoped | Authentication |
| ------------ | ------------- | -------------- |
| Company      | Yes           | Required       |
| Subscription | Yes           | Required       |
| Users        | Yes           | Required       |
| Customers    | Yes           | Required       |
| Dashboard    | Yes           | Required       |
| Plans        | No            | Not required   |
| Health       | No            | Not required   |

---

## Complete Route Summary

```text
GET    /api/v1/health

GET    /api/v1/plans

POST   /api/v1/auth/register-company
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me

GET    /api/v1/company
PUT    /api/v1/company

GET    /api/v1/subscription
PUT    /api/v1/subscription
GET    /api/v1/subscription/usage

GET    /api/v1/users
POST   /api/v1/users
GET    /api/v1/users/{user}
PUT    /api/v1/users/{user}
DELETE /api/v1/users/{user}

GET    /api/v1/customers
POST   /api/v1/customers
GET    /api/v1/customers/{customer}
PUT    /api/v1/customers/{customer}
DELETE /api/v1/customers/{customer}

GET    /api/v1/dashboard
```
