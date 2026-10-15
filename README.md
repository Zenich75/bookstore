# Bookstore Warehouse

An educational Laravel 13 project. It builds a small bookstore warehouse (books, stock,
suppliers, orders) step by step, each stage demonstrating a group of Laravel features.
Lesson notes live in [`docs/`](docs).

## Stack

Laravel 13 · PHP 8.3+ · MySQL 8.4 · Mailpit · Laravel Sail · Blade · Pest · Debugbar

## Getting started

Requirements: Docker and PHP/Composer on the host (or install vendor via the Sail container).

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan queue:work      # in a separate terminal
```

Demo data volume is controlled by `SEED_SCALE` (`small` by default, `full` ≈ 50k books /
200k reviews, ~1.5 min):

```bash
docker compose exec -e SEED_SCALE=full laravel.test php artisan migrate:fresh --seed
```

Seeded accounts (password `password`): `admin@example.com`, `manager1..5@example.com`.

Tip: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`.

| Service | URL |
|---|---|
| App | <http://localhost> |
| Mailpit | <http://localhost:8025> |
| MySQL (host) | `127.0.0.1:3307`, user `sail` / `password` |

Useful commands:

```bash
./vendor/bin/sail artisan test   # Pest test suite
./vendor/bin/sail pint           # code style
./vendor/bin/sail down           # stop containers
```

`MODEL_STRICT` (default `true`) toggles Eloquent strict mode — see
[docs/00-setup.md](docs/00-setup.md).

## Lesson map

| Stage | Topic | Notes |
|---|---|---|
| 0 | Project setup: Sail, Debugbar, strict mode, database queue | [docs/00-setup.md](docs/00-setup.md) |
| 1 | Schema, models (PHP attributes), enums, factories, seeders | [docs/01-schema.md](docs/01-schema.md) |
| 2 | _TBD_ | |
| 3 | _TBD_ | |
| 4 | _TBD_ | |
| 5 | _TBD_ | |
| 6 | _TBD_ | |
| 7 | _TBD_ | |
| 8 | _TBD_ | |
| 9 | _TBD_ | |
| 10 | _TBD_ | |

New lessons start from [docs/_template.md](docs/_template.md).
