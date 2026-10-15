# Bookstore Warehouse — project conventions

Educational Laravel codebase: each stage (lesson) demonstrates a set of Laravel features
on a bookstore warehouse domain. Keep code small, readable and focused on the lesson topic.

## Stack

- Laravel 13, PHP 8.3+ (Sail runtime: PHP 8.5), MySQL 8.4, Mailpit — all via Laravel Sail.
- Blade-only frontend. No Vue/Inertia/Livewire, no auth starter kits.
- Tests: Pest (`sail artisan test`). Feature tests use in-memory SQLite (see `phpunit.xml`).
- Queue: `database` driver (`sail artisan queue:work`).
- Debugbar (dev only) is enabled when `APP_DEBUG=true`.

## Running commands

Always run app commands through Sail: `./vendor/bin/sail artisan ...`, `./vendor/bin/sail composer ...`,
`./vendor/bin/sail test`. MySQL is forwarded to host port 3307 (`FORWARD_DB_PORT`).

## Code conventions

- Prefer PHP attributes on Eloquent models (`#[Table]`, `#[Fillable]`, `#[Hidden]`, ...) over
  properties like `$table`, `$fillable`, `$hidden`.
- When a lesson compares syntaxes (attribute vs. property, new vs. legacy API), keep the legacy
  variant as a commented-out block right next to the modern one, with a short note.
- Code, comments, commit messages and docs are in English.
- Money is stored as integer cents (e.g. `price_cents` as `unsignedInteger`/`bigInteger`), never
  floats or decimals; format only at the presentation layer.
- Eloquent strict mode (`Model::shouldBeStrict()`) is on by default, controlled by `MODEL_STRICT`
  (`config('app.model_strict')`). Do not remove it; lessons that need lazy loading (N+1 demo)
  toggle the flag instead.
- Relationship methods have return types and `@return Relation<Related, $this>` generics docblocks.
  Load relations explicitly (`with`/`load`/relation query) — never rely on lazy loading.
- Every public page gets an `expectConstantQueries()` test (tests/Pest.php) guarding against N+1.
- Polymorphic types use `Relation::enforceMorphMap()` (AppServiceProvider): every new morphable
  model must get an alias there; data migrations use plain alias/class strings, not `::class`.

## Lessons & docs

- Each lesson/stage gets `docs/NN-topic.md` (e.g. `docs/03-relationships.md`) based on
  `docs/_template.md` (Goal, Where to look in code, Key concepts, Exercise).
- Update the lesson map in `README.md` when a stage is added.

## Finishing a task

- Run `./vendor/bin/sail pint` (or `vendor/bin/pint`) before finishing.
- Run the test suite; it must pass.
- No unrelated refactors — change only what the current stage needs.
