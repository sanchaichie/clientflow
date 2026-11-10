# Architecture Decisions

Short records of decisions that are not obvious from the code. Newest last.

## 001 — Nuxt 4 instead of Nuxt 3
The spec names Nuxt 3, which is now in maintenance. Nuxt 4 is the current release and Nuxt UI v4 targets it. The programming model (Vue 3 Composition API, auto-imports, `useFetch`/`$fetch`) is the same; the visible difference is that application code lives in `frontend/app/`.

## 002 — Sanctum SPA cookie authentication (not bearer tokens)
The SPA authenticates with Laravel's session cookie, protected by Sanctum's CSRF cookie (`XSRF-TOKEN`). No token is stored in JavaScript-readable storage, which limits the damage of an XSS bug.
Requirements this creates:
- SPA and API must share a top-level domain (`localhost` in dev). `SESSION_DOMAIN=localhost`, `SANCTUM_STATEFUL_DOMAINS=localhost:3000`.
- CORS must name the SPA origin explicitly (`FRONTEND_URL`) and set `supports_credentials=true`; browsers reject `*` with credentials.
- `bootstrap/app.php` enables `$middleware->statefulApi()`.

## 003 — Frontend runs in SPA mode (`ssr: false`)
Every page sits behind a login, so server rendering brings no SEO benefit. With SSR, the Nuxt server would have to forward the user's cookies to Laravel on every request; in SPA mode the browser talks to Sanctum directly.

## 004 — Tests run against a dedicated MySQL database
The local PHP build has no `pdo_sqlite`, so `phpunit.xml` points tests at `clientflow_test` (MySQL 8.4) instead of in-memory SQLite. This also means tests exercise the same database engine as production.

## 005 — Prettier formats, ESLint checks quality
`@nuxt/eslint` stylistic rules are disabled and `eslint-config-prettier` is applied last, so the two tools never disagree about formatting.

## 006 — Project progress is derived from tasks, cached in a column
`progress = round(completed / total × 100)`, computed by `Project::recalculateProgress()` with one aggregate query. It is stored so list pages and reports don't count tasks per row, and it is not mass-assignable, so the API can never accept it as input. Services recalculate it whenever tasks change (Milestone 4).

## 007 — Clients have an explicit account manager
`clients.account_manager_id` (nullable). A PM manages the clients assigned to them, independent of which projects they run. Full schema rationale is in `docs/ERD.md`.

## 008 — Demo data is environment-guarded
`DatabaseSeeder` always seeds roles (safe in production) but seeds demo accounts — which share the password `password` — only in `local`/`testing`. A test asserts production seeding creates no users.

## 009 — Sensitive columns are not mass-assignable
`users.role_id`, `users.is_active`, `projects.progress`, and the owner columns (`created_by`, comment `user_id`/`task_id`) are excluded from `#[Fillable]`. They are set explicitly by code that has checked permissions, so a request cannot smuggle them in.

## 010 — Permission rules (interpretation of spec §3)
Enforced by policies (`app/Policies`) for single records and by `visibleTo()` scopes for lists — the two are kept in sync and covered by `tests/Feature/Auth/AuthorizationRulesTest.php`. Admins pass every check via `Gate::before`.

| Action | Project Manager | Team Member |
|---|---|---|
| List / view users | ✅ (needed to add members, assign tasks) | ❌ (own profile via `/api/user`) |
| Create / edit / deactivate users | ❌ admin only | ❌ |
| View clients | assigned to them **or** with a project they manage | ❌ (sees client name via project) |
| Create clients | ✅ | ❌ |
| Edit / archive clients | only clients they account-manage | ❌ |
| View projects | ones they manage or are a member of | ones they are a member of |
| Create projects | ✅ | ❌ |
| Edit / archive / manage members | only projects they manage | ❌ |
| View tasks & comment | in projects they can view | in projects they can view |
| Create / edit / delete tasks | in projects they manage | ❌ |
| Change task status | in projects they manage | **only tasks assigned to them** |
| Restore archived clients/projects | ❌ admin only | ❌ |

Interpretation calls not spelled out by the spec: members may *see* all tasks in their projects but move only their own; PMs see other PMs' clients only through shared projects.

## 011 — Auth hardening beyond the spec
- Login is throttled per email+IP (5 attempts), so one attacker cannot lock out every account.
- Wrong password and unknown email return the same error; forgot-password returns the same message whether or not the email exists (no account enumeration).
- Deactivated users are rejected at login and, via the `active` middleware, on every request with an existing session; deactivation also deletes their database sessions.
- Admins create accounts without choosing a password; the new user receives a set-password link (reuses the reset flow).
- Admins cannot demote or deactivate themselves (prevents locking everyone out).
- API 404s return a generic "Resource not found." instead of Laravel's message, which names internal model classes.
- `Model::preventLazyLoading()` is on outside production, turning accidental N+1 queries into errors during development.
