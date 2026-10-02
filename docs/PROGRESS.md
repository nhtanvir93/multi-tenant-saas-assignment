# PROGRESS

Plan approved by owner: **pending**
Current step: **0** (docs delivered, waiting for owner to commit and say "next")
Last updated: 2026-10-02
Rule: one step = one commit. AI stops after each step. See `docs/PLAN.md` section 13.

## Steps
- [x] 0  Plan & handoff docs — `docs: add specs, project plan and handoff docs`
- [ ] 1  Architecture & design patterns doc — `docs: add architecture and design pattern decisions`
- [ ] 2  Schema design doc — `docs: add database schema design`
- [ ] 3  Docker + Laravel scaffold — `chore: scaffold Laravel with single-command Docker setup`
- [ ] 4  Plans (read) + tests — `feat(plans): add plans catalogue endpoint`
- [ ] 5  Tenancy core + tests — `feat(tenancy): add tenant context and global scope`
- [ ] 6  Auth + company registration + tests — `feat(auth): add company registration and token authentication`
- [ ] 7  Company CRUD + policies + tests — `feat(company): add company management with role policies`
- [ ] 8  Subscription, usage, limit enforcement + tests — `feat(subscription): add plans assignment, usage and limit enforcement`
- [ ] 9  Users CRUD + tests — `feat(users): add user management with RBAC and plan limits`
- [ ] 10 Customers CRUD + tests — `feat(customers): add customer management with filtering and limits`
- [ ] 11 Dashboard API + tests — `feat(dashboard): add analytics endpoint`
- [ ] 12 Redis caching + invalidation + tests — `feat(cache): add Redis caching with event-driven invalidation`
- [ ] 13 Background jobs + tests — `feat(jobs): add queued jobs for notifications and cache warming`
- [ ] 14 Rate limiting, hardening, query review + tests — `perf(security): add rate limiting and query optimisation pass`
- [ ] 15 API docs + Postman — `docs(api): add API reference and Postman collection`
- [ ] 16 README + final verification — `docs: add README and final submission checklist`

## In progress
(none)

## Decisions log
- 2026-10-02: PostgreSQL, Sanctum, shared DB + `company_id`, repository only for Customer.
- 2026-10-02: Owner rule: subscriptions are upgrade-only, downgrade always rejected (`DOWNGRADE_NOT_ALLOWED`). Added PLAN section 5a (business rules & restrictions); plans get `tier`, subscriptions keep history (`replaced`).
- 2026-10-02: 17 steps / 17 commits; migrations written with the CRUD that needs them; Docker single command with composer.lock-hash auto install.

## Open decisions awaiting owner
- PostgreSQL vs MySQL (default PostgreSQL)
- Plan-limit HTTP status: 403 + `PLAN_LIMIT_REACHED` (default) vs 422
- CSV export job (stretch, default skip)
- Enterprise limits: unlimited (`NULL`) by default, confirm
- Audit log table for plan/role/delete events (stretch, default skip)

## Known issues / TODO
(none)
