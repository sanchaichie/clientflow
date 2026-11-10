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

## 013 — Frontend auth flow (Milestone 5)
- **One API client** (`plugins/api.ts`, used via `useApi()`): sends cookies, fetches Sanctum's CSRF cookie before the first write and echoes it as `X-XSRF-TOKEN`, and on a 401 clears the user and redirects to `/login?redirect=…`.
- **`auth.global` middleware** asks `GET /api/user` once per page load; pages marked `auth: 'guest'` (login, password reset) are for logged-out users, everything else requires login. A `role` middleware restricts pages by role — a convenience only, since the API enforces the same rules.
- **Redirect targets are sanitized** (`safeRedirect`) so `?redirect=https://…` cannot send users to another site after login (open-redirect protection).
- **No form-validation library:** Nuxt UI's `validate` function gives instant feedback; the API's 422 errors are mapped back onto the same fields (`toFormErrors`). Avoids a Zod/Valibot dependency.

## 014 — Frontend screens (Milestone 5)
- **Dashboard is one request** (`GET /api/dashboard`, `DashboardService`): stats come from two aggregate queries (projects, tasks) instead of many counts. Admins/PMs see figures for everything they can access; team members see figures for *their own* tasks (`scope: my_tasks`) and no client count. Recent activity is wired but stays empty until Milestone 6 writes activity logs.
- **Reusable building blocks:** `usePaginatedList` (page, debounced search, typed filters, stale-response guard), `DataTable` (toolbar, loading/empty/error states, pagination), `StatusBadge` + `utils/options.ts` (one source of labels/colours), `useConfirm` (confirmation before destructive actions), `UserSelect`, and one form modal per resource opened via `useOverlay()`.
- **`usePermissions` mirrors the backend policies** so the UI hides actions the user can't perform; the API still enforces every rule.
- **Kanban** (`vue-draggable-plus` — needed for touch support): moves are optimistic and the board reloads on error. Card positions are only sent when viewing a single project, because order is stored per project. Cards the user may not move are locked.
- **One `TaskBoard`** (table ⇄ Kanban, filters, create/edit/delete) is used on both the Tasks page and each project's page.

## 012 — CRUD API conventions (Milestone 4)
- **Responses:** single records as `{"data": {...}}`, lists as `{"data": [...], "links": {...}, "meta": {...}}` (Laravel API Resources). Creates return 201; archive/delete return 204.
- **Lists:** filters via query string, `?sort=column` / `?sort=-column` against an allow-list, `?per_page=` (max 100; tasks 200 for the Kanban board). Archived clients/projects — and tasks of archived projects — are hidden unless `?archived=1` / `?project_id=` asks for them.
- **Archived records are read-only** for non-admins (policies), including the tasks inside an archived project.
- **Ownership fields are decided by the server:** a PM's new client/project is assigned to them; only admins may send `account_manager_id` / `manager_id`. `progress`, `created_by`, `completed_at` and `position` are never accepted from input.
- **Tasks:** may only be assigned to active project members (or the manager); cannot move between projects. Removing a member unassigns their tasks in that project.
- **Services vs. controller:** `ProjectService` and `TaskService` hold the multi-step, transactional logic (members, Kanban positions, progress). Client CRUD has no such logic and stays in its controller (spec §8: services "when business logic warrants them").
- **N+1 protection has two layers:** resources only print relations via `whenLoaded()` (a missing eager load drops a field — caught by structure assertions — rather than firing queries), and `preventLazyLoading()` turns any direct lazy access into an error. `QueryPerformanceTest` asserts list endpoints run the same number of queries for 2 and 12 rows.
