# 00 — Project setup

## Goal

Have a reproducible local environment (Docker via Sail) with debugging tools, strict Eloquent
behavior and a database-backed queue, so later lessons can focus on Laravel features only.

## Where to look in code

- `compose.yaml` — Sail services: `laravel.test` (PHP 8.5 app), `mysql` (8.4), `mailpit`.
- `.env.example` — Sail-ready defaults (DB, mail, queue, ports, `MODEL_STRICT`).
- `config/app.php` — `model_strict` config key.
- `app/Providers/AppServiceProvider.php` — `Model::shouldBeStrict()`.
- `database/migrations/0001_01_01_*` — default `users`, `sessions`, `cache`, `jobs`,
  `job_batches`, `failed_jobs` tables.
- `tests/Pest.php`, `tests/Feature/StrictModeTest.php` — Pest setup and a strict-mode check.

## Key concepts

### Laravel Sail

A thin CLI over Docker Compose. Everyone gets identical PHP, MySQL and Mailpit versions without
installing them on the host. Installed with `php artisan sail:install --with=mysql,mailpit`.

- App: <http://localhost> (`APP_PORT`)
- Mailpit UI: <http://localhost:8025> — every mail sent with `MAIL_MAILER=smtp` lands here.
- MySQL from the host: `127.0.0.1:3307` (`FORWARD_DB_PORT`; 3307 to avoid clashing with other
  local MySQL instances). Inside containers the app connects to `mysql:3306`.

### Debugbar (`barryvdh/laravel-debugbar`)

Shows queries, timeline, views, mails, etc. at the bottom of each HTML page. It is a
`require-dev` dependency (absent in `composer install --no-dev` builds) and is enabled only when
`APP_DEBUG=true` (its `DEBUGBAR_ENABLED` defaults to `APP_DEBUG`). It will be the main tool to
*see* N+1 queries in later lessons.

### Eloquent strict mode

`Model::shouldBeStrict(true)` turns on three protections:

| Protection | What throws |
|---|---|
| `preventLazyLoading` | accessing a relation that wasn't eager loaded (N+1) |
| `preventSilentlyDiscardingAttributes` | mass-assigning an attribute not in `$fillable` / `#[Fillable]` |
| `preventAccessingMissingAttributes` | reading an attribute that wasn't selected / doesn't exist |

It is controlled by the `MODEL_STRICT` env flag (default `true`), read through
`config('app.model_strict')` so it keeps working with `config:cache`. Later lessons set
`MODEL_STRICT=false` to demonstrate the N+1 problem, then fix it with eager loading.

```bash
./vendor/bin/sail artisan tinker --execute="dump(config('app.model_strict'), Illuminate\Database\Eloquent\Model::preventsLazyLoading());"
```

### Database queue

`QUEUE_CONNECTION=database` stores jobs in the `jobs` table and failures in `failed_jobs` — no
Redis needed, and you can inspect queued jobs with plain SQL. Run a worker with
`./vendor/bin/sail artisan queue:work`. Cache and sessions also use the database.

### Pest & Pint

- Pest replaces the default PHPUnit-style tests (`./vendor/bin/sail artisan test`).
- Pint formats code to the Laravel style (`./vendor/bin/sail pint`); run before committing.

## Exercise

1. Set `MODEL_STRICT=false` in `.env`, then check in tinker that
   `Model::preventsLazyLoading()` returns `false`. Restore it.
2. Send a test mail from tinker:
   `Mail::raw('Hello', fn ($m) => $m->to('test@example.com'));` and find it in Mailpit.
3. Dispatch a closure job (`dispatch(fn () => logger('queued'));`), look at the `jobs` table,
   then run `queue:work` and check `storage/logs/laravel.log`.
