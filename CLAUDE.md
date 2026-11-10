# ClientFlow — Development Rules

Client & project management SaaS. Source of truth: `docs/ClientFlow_Project_Specification_Updated.pdf`.
Design records: `docs/ERD.md` (schema), `docs/DECISIONS.md` (architecture and permission decisions).

## Stack & layout
- `frontend/` — Nuxt (Vue 3 Composition API, TypeScript, Nuxt UI), SPA mode. **Nuxt 4** is used instead of the spec's Nuxt 3 (approved; see DECISIONS 001).
- `backend/` — Laravel REST API, Sanctum (SPA cookie auth), Eloquent, MySQL 8.4.
- Keep frontend and backend separate; they talk only through `/api/*`.

## Backend rules
- Follow Laravel conventions; keep controllers thin. Use service classes only when there is multi-step or transactional logic.
- Validation: Form Requests (`app/Http/Requests`).
- Authorization: Policies (`app/Policies`) for single records **and** `visibleTo()` scopes for lists. Enforce access in the API — hiding UI controls is never enough.
- Responses: API Resources (`app/Http/Resources`); relations only via `whenLoaded()`; eager-load to avoid N+1.
- Schema changes: migrations + factories + seeders. Never edit a migration that has been committed; add a new one.
- Write feature tests for important behaviour, including unauthorized access.

## Frontend rules
- Reusable components; Nuxt UI first. Handle loading, empty and error states.
- Avoid new dependencies unless clearly justified.

## Safety
- Never commit passwords, API keys or real `.env` values; only `.env.example` with placeholders.
- **Never run destructive database commands without explicit approval** (`migrate:fresh`, `migrate:reset`, `db:wipe`, `DROP`, mass `DELETE`/`TRUNCATE`) on the dev database `clientflow`.
  The test database `clientflow_test` is reset automatically by `RefreshDatabase` during `php artisan test`; that is expected.

## Workflow
- One milestone at a time; wait for approval before starting the next major milestone.
- Before implementing: explain the approach and the files involved.
- After implementing: list changed files with a one-line explanation each.
- Run relevant tests and report the actual results — including failures.
- Commit only after approval, with organized messages.

## Commands
```bash
# backend/
php artisan test                 # tests (MySQL clientflow_test)
./vendor/bin/pint --test         # code style
php artisan serve --host=localhost --port=8000

# frontend/
npm run dev                      # http://localhost:3000
npm run lint && npm run typecheck
npm run format
```
Demo accounts (local only, password `password`): `admin@` / `pm@` / `member@clientflow.test`.
