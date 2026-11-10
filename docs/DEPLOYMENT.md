# Deployment notes & production checklist

ClientFlow is two deployables: the **Laravel API** (PHP app server + MySQL) and the
**Nuxt SPA** (static files after `nuxt generate`, or `nuxt build` + Node). Because login
uses Sanctum **cookie** sessions, both must live on the same registrable domain, e.g.
`app.example.com` (SPA) and `api.example.com` (API).

## Backend `.env` (production values)

| Key | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | disables demo seeding, enables lazy-loading guard only in dev |
| `APP_DEBUG` | **`false`** | otherwise error responses include stack traces and file paths |
| `APP_KEY` | generate once: `php artisan key:generate` | encrypts sessions/cookies — never commit it |
| `APP_URL` | `https://api.example.com` | |
| `APP_TIMEZONE` | the agency's zone, e.g. `Asia/Manila` | "today", overdue, due-soon reminders and report ranges use it. **Set before go-live** — timestamps are stored in this zone |
| `FRONTEND_URL` | `https://app.example.com` | CORS origin + links in emails |
| `SANCTUM_STATEFUL_DOMAINS` | `app.example.com` | which origins get cookie sessions |
| `SESSION_DOMAIN` | `.example.com` | cookie shared by SPA and API subdomains |
| `SESSION_SECURE_COOKIE` | `true` | cookies only over HTTPS |
| `SESSION_ENCRYPT` | `true` (recommended) | |
| `DB_*` | a dedicated MySQL user with rights on this database only | never `root` |
| `MAIL_*` | real SMTP / provider credentials | invites and password resets |
| `LOG_LEVEL` | `warning` or `error` | |
| `BCRYPT_ROUNDS` | `12` (default) | |

Secrets live only in the server's `.env` (or the host's secret store) — `.env.example` holds placeholders.

## Frontend `.env`

| Key | Value |
|---|---|
| `NUXT_PUBLIC_API_BASE` | `https://api.example.com` |

## Deploy steps

**API**
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # never migrate:fresh in production
php artisan db:seed --class=RoleSeeder --force   # roles only; demo data never runs in production
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan storage:link             # if file uploads are added later
```
Create the first administrator once (e.g. `php artisan tinker`), then invite everyone else from the Team page.

**SPA**
```bash
npm ci
npx nuxt generate       # static output in .output/public (SPA mode, ssr: false)
```
Serve `.output/public` from any static host/CDN with a fallback of all routes to `index.html`.

**Background processes**
- Scheduler (deadline reminders, daily 08:00): cron `* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`
- Queue worker: **not required** today — notifications are written synchronously (DECISIONS 015). If email/push
  notifications are added as queued channels, run `php artisan queue:work` under a supervisor.

## Production-readiness checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, fresh `APP_KEY`
- [ ] `APP_TIMEZONE` set to the agency's timezone (before any data is created)
- [ ] HTTPS everywhere; `SESSION_SECURE_COOKIE=true`; SPA and API on the same registrable domain
- [ ] `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` set to the real hosts (CORS uses `FRONTEND_URL`)
- [ ] Dedicated least-privilege MySQL user; automated backups with a tested restore
- [ ] `php artisan migrate --force` in the deploy pipeline; **no** `migrate:fresh`/`db:seed` of demo data
- [ ] Mail provider configured and verified (send a password reset)
- [ ] Scheduler cron installed (`schedule:list` shows `tasks:notify-due-soon`)
- [ ] `config:cache` / `route:cache` run on every deploy
- [ ] Log monitoring and error alerts (e.g. via a log aggregator)
- [ ] CI runs `php artisan test`, `pint --test`, `npm test`, `npm run lint`, `npm run typecheck`
- [ ] Dependency audits reviewed (below)

## Dependency audit status (2026-11-11)

- `composer audit`: **no advisories**.
- `npm audit`: 7 low / 23 moderate / 0 high / 0 critical. Every finding is in **build-time or
  dev-server tooling** (nitropack/vite internals, glob/minimatch, archiver, eslint, deepmerge, esbuild) —
  none of these packages are part of the JavaScript shipped to browsers. `esbuild`'s dev-server
  file-read issue only applies when the dev server is exposed on a network; ours binds to `localhost`.
- `npm audit fix --force` is **not** used: its "fixes" would *downgrade* Nuxt (4.6 → 4.1) and ESLint
  (10 → 9). Re-run `npm audit` after each Nuxt/ESLint upgrade and apply normal `npm update`s.

## Known limitations / next steps

- In-app notifications only (no email/push); the bell polls every 60 s instead of using websockets.
- Clients and projects are archived, never hard-deleted, via the API; tasks are deleted permanently.
- Reports cover records *created* in the selected range (overdue figures are "overdue now"); there is no historical snapshotting.
- One agency timezone for everyone (`APP_TIMEZONE`); per-user timezones are not supported.
- Optional spec §16 AI features (task description generator, progress summaries) are not implemented.
