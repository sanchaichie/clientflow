# ClientFlow API

Base URL: `http://localhost:8000/api` (all routes are defined in `backend/routes/api.php`).

## Conventions

| Topic | Behaviour |
|---|---|
| Auth | Sanctum **SPA cookie** sessions. First `GET /sanctum/csrf-cookie`, then send the `X-XSRF-TOKEN` header (value of the `XSRF-TOKEN` cookie) on every write, with credentials included. Requests must come from `SANCTUM_STATEFUL_DOMAINS`. |
| Format | Send `Accept: application/json`. Single records: `{"data": {...}}`. Lists: `{"data": [...], "links": {...}, "meta": {"current_page", "last_page", "per_page", "total", ...}}`. |
| Lists | `?page=`, `?per_page=` (max 100; tasks 200), `?search=`, filters per resource, `?sort=column` or `?sort=-column` (allow-listed columns; unknown values fall back to the default). |
| Archive | `DELETE` on clients and projects **archives** (sets `archived_at`); archived records are hidden unless `?archived=1` and are read-only for non-admins. `POST …/restore` is admin-only. |
| Errors | `401` not logged in · `403` not allowed (or account deactivated) · `404` `{"message":"Resource not found."}` · `419` CSRF token missing/expired · `422` `{"message", "errors": {"field": ["…"]}}` · `429` too many attempts |
| Authorization | Enforced on every endpoint by policies; list endpoints only ever return records the user may see. Role rules: `docs/DECISIONS.md` § 010. |

Values used below — **roles** `admin` `project_manager` `team_member` · **client status** `lead` `active` `inactive` ·
**project status** `planning` `active` `on_hold` `completed` `cancelled` · **task status** `todo` `in_progress` `review` `completed` ·
**priority** `low` `medium` `high` `urgent` · **comment type** `comment` `work_update`.

---

## Authentication

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| POST | `/login` | guest | `{email, password, remember?}` → user with role. 5 failed attempts per email+IP → `429`. Deactivated → `403`. Wrong email *or* password → same `422`. |
| POST | `/logout` | user | `204` |
| GET | `/user` | user | current user incl. `role` |
| POST | `/forgot-password` | guest | `{email}` → same message whether or not the email exists. Max 6/min per IP. |
| POST | `/reset-password` | guest | `{token, email, password, password_confirmation}` |

## Dashboard & reports

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| GET | `/dashboard` | user | `stats` (clients, active/completed projects, pending/overdue/due-today tasks), `project_progress`, `upcoming_tasks`, `recent_activity`. Team members get their own task figures (`scope: "my_tasks"`) and `total_clients: null`. |
| GET | `/reports?from=YYYY-MM-DD&to=YYYY-MM-DD` | admin, PM | `projects_by_status`, `tasks_by_status`, `completion`, `members[]` and `unassigned` cover records *created* in the period (days in `APP_TIMEZONE`); `overdue {count, tasks}` and each member's `overdue` are **overdue now**, regardless of the period. Team members → `403`. |

## Users (admin-controlled registration)

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| GET | `/users?search=&role=&active=` | admin, PM | directory |
| POST | `/users` | admin | `{name, email, role}` → `201`; the user is emailed a set-password link |
| GET | `/users/{id}` | admin, PM, self | |
| PUT | `/users/{id}` | admin | `{name?, email?, role?, is_active?}`; cannot demote/deactivate yourself |
| DELETE | `/users/{id}` | admin | **deactivates** and ends their sessions (`204`) |

## Clients

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| GET | `/clients?search=&status=&archived=&sort=` | admin, PM | PMs see clients they account-manage or that have a project they manage |
| POST | `/clients` | admin, PM | `{company_name, status, contact_person?, email?, phone?, address?, description?, account_manager_id? (admin only)}`; a PM's client is assigned to them |
| GET | `/clients/{id}` | admin, PM (visible) | includes the client's projects *visible to the caller* |
| PUT | `/clients/{id}` | admin, account manager | `account_manager_id` admin-only |
| DELETE | `/clients/{id}` | admin, account manager | archive |
| POST | `/clients/{id}/restore` | admin | |

## Projects & members

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| GET | `/projects?search=&status=&client_id=&manager_id=&archived=&sort=` | user | only projects you manage or belong to (admins: all); includes `client`, `manager`, `members_count`, `tasks_count` |
| POST | `/projects` | admin, PM | `{client_id, name, description?, start_date?, end_date?, status?, member_ids?[], manager_id (admin only, required)}`; a PM's project is managed by them. `progress` is derived from tasks and never accepted. |
| GET | `/projects/{id}` | manager, members, admin | includes `members` |
| PUT | `/projects/{id}` | manager, admin | same fields (no members); `manager_id` admin-only |
| DELETE | `/projects/{id}` | manager, admin | archive (tasks become read-only) |
| POST | `/projects/{id}/restore` | admin | |
| POST | `/projects/{id}/members` | manager, admin | `{user_ids: []}` — active team members / PMs; existing members are skipped |
| DELETE | `/projects/{id}/members/{userId}` | manager, admin | also unassigns their tasks in this project |

## Tasks

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| GET | `/tasks?project_id=&status=&priority=&assignee_id=(id\|me)&due=(overdue\|today)&search=&sort=` | user | tasks in visible projects; archived projects' tasks only with `project_id` |
| POST | `/tasks` | project manager, admin | `{project_id, title, description?, status?, priority?, due_date?, assignee_id?}` — assignee must be a project member or the manager |
| GET | `/tasks/{id}` | anyone who can see the project | |
| PUT | `/tasks/{id}` | project manager, admin | details incl. assignee/status; a task cannot change project |
| PATCH | `/tasks/{id}/status` | project manager, admin, **assignee** | `{status, position?}` — Kanban move; `position` = index in the target column |
| DELETE | `/tasks/{id}` | project manager, admin | permanent |

Side effects of task writes (in one transaction): `completed_at`, Kanban positions, the project's `progress`,
an activity-log entry, and notifications.

## Comments, activity, notifications

| Method | Endpoint | Who | Notes |
|---|---|---|---|
| GET | `/tasks/{id}/comments` | anyone who can see the task | oldest first, with `author` |
| POST | `/tasks/{id}/comments` | anyone who can see the task (open projects) | `{body, type?: comment\|work_update}` → `201` |
| DELETE | `/comments/{id}` | author, admin | |
| GET | `/activity?project_id=&task_id=` | user | newest first, limited to visible projects; `properties` holds a snapshot (e.g. task title) and change details |
| GET | `/notifications?unread=1` | user | own notifications; `meta.unread_count` |
| GET | `/notifications/unread-count` | user | `{data: {unread_count}}` — polled by the bell |
| PATCH | `/notifications/{id}/read` | user | someone else's id → `404` |
| POST | `/notifications/read-all` | user | |

Notification `kind`s: `task_assigned`, `task_status_changed`, `task_commented`, `project_assigned`, `task_due_soon`.
Each carries `message` and a frontend `url`. The acting user is never notified about their own action.
