# Final Submission Checklist

## 1. Application Boot

- [ ] `docker compose up --build` completes successfully
- [ ] All expected containers are healthy
- [ ] Nginx is reachable
- [ ] Laravel application boots without errors
- [ ] PostgreSQL is reachable
- [ ] Redis is reachable
- [ ] Queue worker starts
- [ ] Scheduler starts

## 2. Database

- [ ] Migrations complete successfully
- [ ] Required plans are seeded
- [ ] Foreign keys exist
- [ ] Unique constraints exist
- [ ] Tenant indexes exist
- [ ] Customer soft-delete indexes exist
- [ ] Subscription active-record constraint exists

## 3. Authentication

- [ ] Company registration works
- [ ] Registration creates company, owner and initial subscription atomically
- [ ] Login works
- [ ] Invalid credentials return the expected generic authentication failure
- [ ] `auth/me` works
- [ ] Logout revokes the current token
- [ ] Inactive users cannot authenticate

## 4. Tenancy

- [ ] Tenant context is created from the authenticated user
- [ ] `company_id` is never trusted from client input
- [ ] Tenant global scope is active
- [ ] Missing tenant context fails closed
- [ ] Cross-tenant reads are blocked
- [ ] Cross-tenant updates are blocked
- [ ] Cross-tenant deletes are blocked
- [ ] Tenant route binding does not leak resource existence
- [ ] Tenant cache keys are isolated
- [ ] Tenant-aware jobs restore context before database access

## 5. Authorization

- [ ] Company policy is enforced
- [ ] User policy is enforced
- [ ] Customer policy is enforced
- [ ] Subscription policy is enforced
- [ ] Owner-only operations are protected
- [ ] Admin restrictions are protected
- [ ] Regular-user restrictions are protected

## 6. Subscription and Limits

- [ ] Plan listing works
- [ ] Current subscription works
- [ ] Subscription history is retained
- [ ] Upgrade rules work
- [ ] Invalid downgrade is rejected
- [ ] Same-plan update is rejected where required
- [ ] User limit is enforced
- [ ] Customer limit is enforced
- [ ] Unlimited plans are handled correctly
- [ ] Usage endpoint works

## 7. Users

- [ ] User list works
- [ ] User creation works
- [ ] User details work
- [ ] User update works
- [ ] User deletion works
- [ ] User pagination works
- [ ] User authorization works
- [ ] User limit boundary is tested
- [ ] Invitation job is dispatched

## 8. Customers

- [ ] Customer list works
- [ ] Customer creation works
- [ ] Customer details work
- [ ] Customer update works
- [ ] Customer deletion works
- [ ] Search works
- [ ] Status filtering works
- [ ] Sorting works
- [ ] Pagination works
- [ ] Customer limit boundary is tested
- [ ] Tenant isolation is tested

## 9. Dashboard

- [ ] Dashboard endpoint works
- [ ] Dashboard data is tenant-scoped
- [ ] Dashboard authorization works
- [ ] Dashboard cache works
- [ ] Dashboard cache lock prevents duplicate rebuilds

## 10. Caching

- [ ] Tenant cache keys contain tenant identity
- [ ] Cache versioning works
- [ ] Customer cache works
- [ ] User cache invalidation works
- [ ] Customer cache invalidation works
- [ ] Subscription cache invalidation works
- [ ] Dashboard cache invalidation works
- [ ] Invalidation occurs after committed writes
- [ ] Cached repository delegates non-cached operations correctly

## 11. Background Jobs

- [ ] Welcome email job dispatches
- [ ] Invitation job dispatches
- [ ] Limit threshold notification dispatches
- [ ] Cache warming job dispatches
- [ ] Retry configuration is present
- [ ] Jobs establish tenant context
- [ ] Jobs do not leak tenant context
- [ ] Queue tests pass

## 12. Rate Limiting

- [ ] Authentication limiter works
- [ ] Authentication key uses normalized email
- [ ] API limiter works
- [ ] API limiter distinguishes authenticated users
- [ ] Unauthenticated API requests use IP-based limiting
- [ ] Export limiter is configured
- [ ] 429 behaviour is covered where applicable

## 13. Query Safety

- [ ] Lazy loading is prevented outside production
- [ ] Relevant relationships are explicitly loaded
- [ ] N+1-sensitive paths have query-count coverage
- [ ] Tenant-leading indexes exist
- [ ] No unnecessary indexes were added
- [ ] Pagination is bounded
- [ ] PostgreSQL query/index review is documented

## 14. API Documentation

- [ ] `docs/API.md` exists
- [ ] Authentication endpoints are documented
- [ ] Company endpoints are documented
- [ ] Subscription endpoints are documented
- [ ] User endpoints are documented
- [ ] Customer endpoints are documented
- [ ] Dashboard endpoint is documented
- [ ] Tenant isolation behaviour is documented
- [ ] Rate limiting is documented
- [ ] Postman collection exists
- [ ] Postman collection imports successfully

## 15. Quality Gates

Run:

```bash
make test
```

```bash
docker compose exec app vendor/bin/phpstan analyse
```

```bash
docker compose exec app vendor/bin/pint --test
```

All three must pass before the final commit.

## 16. Repository Hygiene

- [ ] No `.env` secrets committed
- [ ] No generated credentials committed
- [ ] No debug statements remain
- [ ] No temporary test modifications remain
- [ ] No tests were deleted
- [ ] No unrelated refactors were introduced
- [ ] Git diff has been reviewed
- [ ] Documentation matches the implementation
- [ ] `docs/PROGRESS.md` is updated

## 17. Final Git Check

```bash
git status
git diff --check
git diff --stat
git diff
```

Confirm that only the intended Step 16 documentation changes are included.

## 18. Final Commit

```text
docs: add README and final submission checklist
```
