# RESUME PROMPT (paste into a new AI session)

You are continuing development of a Laravel 13 multi-tenant SaaS backend (company assessment). The owner commits to Git after every step so the company can see how the project was built.

1. Read in order: `docs/specs/*` (company spec = source of truth), `docs/PLAN.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/CONVENTIONS.md`, `docs/PROGRESS.md`.
2. Take the first unchecked step in `docs/PROGRESS.md` (roadmap: `docs/PLAN.md` section 13). Do ONLY that step.
3. Follow `docs/CONVENTIONS.md`: tenant scoping, thin controllers, services, cache rules, no needless abstraction, CRUD order (migration -> model -> service -> request -> policy -> resource -> controller -> tests).
   3b. Business rules in `docs/PLAN.md` section 5a are mandatory (e.g. subscriptions are upgrade-only, one owner per company, owner protected). Implement each with its error code and a test.
4. Docker: stack starts with `docker compose up`; add packages with `docker compose exec app composer require ...` and commit `composer.json` + `composer.lock`; extensions go in the Dockerfile. Tests: `make test`.
   4b. The owner's repo is the source of truth for Docker/config files (see `docs/PROGRESS.md` decisions log, BASELINE CHANGE). Ask for or read the current file before touching `Dockerfile`, `docker/entrypoint.sh`, `phpunit.xml`, `.env.testing`, `config/*`, CI. Apply the follow-ups under 'Known issues / TODO' first.
5. When the step is done: tests written for it, docs/README section extended, `docs/PROGRESS.md` updated (done / in progress / next / issues), then give the suggested commit message and STOP. Wait for the owner to say "next".
6. Never batch steps, never rewrite earlier commits' scope. Record decision changes in `docs/PLAN.md`.
7. The owner must be able to explain every decision in review, so keep explanations of architecture, schema, caching, invalidation, indexing and trade-offs current in the docs as you go.
