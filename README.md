# ClientFlow

Client and project management for freelancers, virtual assistants and small VA agencies:
clients, projects, tasks (table + Kanban), comments and work updates, activity history,
in-app notifications and reports — with role-based access enforced by the API.

| Layer | Stack |
|---|---|
| Frontend (`frontend/`) | Nuxt 4 (SPA mode), Vue 3 Composition API, TypeScript, Nuxt UI 4 |
| Backend (`backend/`) | Laravel 13 REST API, Sanctum (SPA cookie auth), Eloquent |
| Database | MySQL 8 |

Further reading: [`docs/ERD.md`](docs/ERD.md) (schema) · [`docs/API.md`](docs/API.md) (endpoints) ·
[`docs/DECISIONS.md`](docs/DECISIONS.md) (architecture & permission rules) ·
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) (production checklist).

---

## Requirements

- PHP **8.3+** with `pdo_mysql`, `mbstring`, `openssl`, `intl`, `bcmath`, `fileinfo`
- Composer 2
- Node.js **22+** and npm
- MySQL **8.0+**
- Optional: [Mailpit](https://mailpit.axllent.org/) (bundled with Laragon) to see password-reset and invite emails locally

> Developed on Windows with Laragon (PHP 8.4, MySQL 8.4, Node 22). Any equivalent setup works.

## Setup

### 1. Database

Create two empty databases — one for the app, one used only by the test suite:

```sql
CREATE DATABASE clientflow      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE clientflow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend (Laravel API)

```bash
cd backend
composer install
cp .env.example .env          # set DB_USERNAME / DB_PASSWORD if not root/empty, and APP_TIMEZONE
php artisan key:generate
php artisan migrate --seed    # tables + roles + demo data (local env only)
php artisan serve --host=localhost --port=8000
```

### 3. Frontend (Nuxt)

```bash
cd frontend
npm install
cp .env.example .env          # NUXT_PUBLIC_API_BASE=http://localhost:8000
npm run dev                   # http://localhost:3000
```

> **Use `localhost` for both apps** (not `127.0.0.1`). Login uses Sanctum session cookies,
> which only work when the SPA and API share the same host. See DECISIONS 002.

### 4. Log in

Demo accounts are created by the seeder **only in `local`/`testing`**. All use the password `password`:

| Role | Email |
|---|---|
| Administrator | `admin@clientflow.test` |
| Project Manager | `pm@clientflow.test` |
| Team Member | `member@clientflow.test` |

The PM and the member start with a short demo conversation and unread notifications.

### 5. Optional background processes

```bash
php artisan schedule:work   # runs the daily 08:00 "task due soon" reminders
```

Emails (invites, password resets) go to `MAIL_HOST:MAIL_PORT` — Mailpit on `127.0.0.1:1025`
with its inbox at http://localhost:8025.

## Development commands

```bash
# backend/
php artisan test               # 218 feature/unit tests (uses the clientflow_test database)
./vendor/bin/pint --test       # code style (Laravel Pint)
php artisan migrate:fresh --seed   # ⚠ wipes and rebuilds the local database

# frontend/
npm test                       # Vitest: tests/unit + tests/nuxt
npm run lint                   # ESLint
npm run typecheck              # vue-tsc
npm run format                 # Prettier
```

## Roles at a glance

| | Admin | Project Manager | Team Member |
|---|---|---|---|
| Users | manage | view directory | — |
| Clients | all | own (account manager) + clients of own projects | — |
| Projects | all | manages / member of | member of |
| Tasks | all | create/edit/delete in own projects | view project tasks, move **own** tasks |
| Comments | all | in visible projects | in visible projects |
| Reports | all | own projects | — |

Every rule is enforced by the API (policies + `visibleTo()` query scopes); the UI only hides
what the API would reject. Full table: `docs/DECISIONS.md` § 010.

## Project structure

```
backend/
  app/Enums            statuses, priorities, roles
  app/Http/Controllers/Api   thin controllers
  app/Http/Requests    validation (Form Requests)
  app/Http/Resources   JSON shapes (API Resources)
  app/Policies         authorization rules
  app/Services         multi-step logic in transactions (tasks, projects, reports, …)
  app/Notifications    in-app notifications
  tests/Feature        API, auth, role matrix, collaboration, database tests
frontend/app/
  composables          useApi, useAuth, usePermissions, usePaginatedList, …
  components           DataTable, forms/, tasks/ (board, Kanban, comments), charts/
  pages                dashboard, clients, projects, tasks, team, reports, notifications
docs/                  ERD, API, decisions, deployment
```
