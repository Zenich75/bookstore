# 00 — Налаштування проєкту

## Мета

Мати відтворюване локальне середовище (Docker через Sail) з інструментами налагодження, суворою
поведінкою Eloquent і чергою в базі даних, щоб наступні уроки зосереджувалися лише на
можливостях Laravel.

## Де дивитися в коді

- `compose.yaml` — сервіси Sail: `laravel.test` (застосунок на PHP 8.5), `mysql` (8.4), `mailpit`.
- `.env.example` — налаштування для Sail (БД, пошта, черга, порти, `MODEL_STRICT`, локаль).
- `config/app.php` — ключ конфігурації `model_strict`.
- `app/Providers/AppServiceProvider.php` — `Model::shouldBeStrict()`.
- `database/migrations/0001_01_01_*` — стандартні таблиці `users`, `sessions`, `cache`, `jobs`,
  `job_batches`, `failed_jobs`.
- `tests/Pest.php`, `tests/Feature/StrictModeTest.php` — налаштування Pest і перевірка суворого режиму.

## Ключові поняття

### Laravel Sail

Тонка обгортка над Docker Compose. Усі отримують однакові версії PHP, MySQL і Mailpit, нічого не
встановлюючи на хост. Встановлено командою `php artisan sail:install --with=mysql,mailpit`.

- Застосунок: <http://localhost> (`APP_PORT`)
- Інтерфейс Mailpit: <http://localhost:8025> — сюди потрапляє кожен лист, надісланий із `MAIL_MAILER=smtp`.
- MySQL з хоста: `127.0.0.1:3307` (`FORWARD_DB_PORT`; 3307, щоб не конфліктувати з іншими
  локальними MySQL). Усередині контейнерів застосунок підключається до `mysql:3306`.

### Debugbar (`barryvdh/laravel-debugbar`)

Показує внизу кожної HTML-сторінки запити, часову шкалу, представлення, листи тощо. Це залежність
`require-dev` (її немає у збірках `composer install --no-dev`), і вона вмикається лише за
`APP_DEBUG=true` (`DEBUGBAR_ENABLED` за замовчуванням дорівнює `APP_DEBUG`). У наступних уроках
це головний інструмент, щоб *побачити* N+1 запити.

### Суворий режим Eloquent (strict mode)

`Model::shouldBeStrict(true)` вмикає три запобіжники:

| Запобіжник | Коли кидає виняток |
|---|---|
| `preventLazyLoading` | звернення до зв’язку, який не завантажили жадібно (N+1) |
| `preventSilentlyDiscardingAttributes` | масове присвоєння атрибута, якого немає в `$fillable` / `#[Fillable]` |
| `preventAccessingMissingAttributes` | читання атрибута, який не вибрали або якого не існує |

Ним керує прапорець середовища `MODEL_STRICT` (за замовчуванням `true`), який читається через
`config('app.model_strict')`, тож працює і з `config:cache`. У наступних уроках ставимо
`MODEL_STRICT=false`, щоб показати проблему N+1, а потім виправляємо її жадібним завантаженням.

```bash
./vendor/bin/sail artisan tinker --execute="dump(config('app.model_strict'), Illuminate\Database\Eloquent\Model::preventsLazyLoading());"
```

### Черга в базі даних

`QUEUE_CONNECTION=database` зберігає задачі в таблиці `jobs`, а збої — у `failed_jobs`: Redis не
потрібен, а поставлені в чергу задачі можна переглянути звичайним SQL. Обробник запускається
командою `./vendor/bin/sail artisan queue:work`. Кеш і сесії теж зберігаються в базі.

### Pest і Pint

- Pest замінює стандартні тести в стилі PHPUnit (`./vendor/bin/sail artisan test`).
- Pint форматує код за стилем Laravel (`./vendor/bin/sail pint`); запускайте перед комітом.

### Локалізація

Інтерфейс українською: `APP_LOCALE=uk`, резервна мова `en`, Faker — `uk_UA`. Тексти — у
`lang/{uk,en}`, терміни — у [глосарії](glossary.md). Детальніше — у розділі «Мова інтерфейсу» в
`README.md`.

## Вправа

1. Встановіть `MODEL_STRICT=false` у `.env` і перевірте в tinker, що
   `Model::preventsLazyLoading()` повертає `false`. Поверніть як було.
2. Надішліть тестовий лист із tinker:
   `Mail::raw('Hello', fn ($m) => $m->to('test@example.com'));` і знайдіть його в Mailpit.
3. Поставте в чергу задачу-замикання (`dispatch(fn () => logger('queued'));`), подивіться на таблицю
   `jobs`, потім запустіть `queue:work` і перевірте `storage/logs/laravel.log`.
