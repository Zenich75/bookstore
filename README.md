# Книжковий склад (Bookstore Warehouse)

Навчальний проєкт на Laravel 13. Крок за кроком будуємо невеликий книжковий склад (книги,
залишки, постачальники, замовлення); кожен етап демонструє групу можливостей Laravel.
Конспекти уроків — у [`docs/`](docs), терміни — у [глосарії](docs/glossary.md).

## Стек

Laravel 13 · PHP 8.3+ · MySQL 8.4 · Mailpit · Laravel Sail · Blade · Pest · Debugbar

## Запуск

Потрібні Docker і PHP/Composer на хості (або встановіть `vendor` через контейнер Sail).

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan queue:work      # in a separate terminal
npm install && npm run build              # Tailwind assets (or `npm run dev`)
```

Обсяг демонстраційних даних задає `SEED_SCALE` (`small` за замовчуванням, `full` ≈ 50 тис. книг /
200 тис. відгуків, ~1,5–2 хв):

```bash
docker compose exec -e SEED_SCALE=full laravel.test php artisan migrate:fresh --seed
```

Облікові записи з сидерів (пароль `password`): `admin@example.com`, `manager1..5@example.com`.

Порада: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`.

| Сервіс | Адреса |
|---|---|
| Застосунок | <http://localhost> |
| Mailpit | <http://localhost:8025> |
| Лабораторія зв’язків | <http://localhost/lab/relations> |
| Лабораторія N+1 | <http://localhost/lab/n-plus-one> |
| Лабораторія колекцій | <http://localhost/lab/collections> |
| Лабораторія моделей | <http://localhost/lab/models> |
| Звіти | <http://localhost/reports/stock>, <http://localhost/reports/top-authors>, `/reports/stock.csv` |
| MySQL (з хоста) | `127.0.0.1:3307`, користувач `sail` / `password` |

Корисні команди:

```bash
./vendor/bin/sail artisan test   # Pest test suite
./vendor/bin/sail pint           # code style
./vendor/bin/sail down           # stop containers
```

`MODEL_STRICT` (за замовчуванням `true`) вмикає суворий режим Eloquent — див.
[docs/00-setup.md](docs/00-setup.md).

## Мова інтерфейсу

Інтерфейс, лабораторії та документація — українською; код, ідентифікатори й коментарі в коді —
англійською.

- `APP_LOCALE=uk`, `APP_FALLBACK_LOCALE=en`, `APP_FAKER_LOCALE=uk_UA`.
- Тексти інтерфейсу — у `lang/{uk,en}/{ui,lab,reports,enums}.php`, у шаблонах — лише `__()` і
  `trans_choice()` (українські форми множини: 1 книга / 2 книги / 5 книг).
- Повідомлення фреймворку (`validation`, `auth`, `pagination`, `passwords`) — пакет
  `laravel-lang/common` (`php artisan lang:add uk`). Команда `lang:update` може перезаписати
  ручні правки в цих файлах.
- Назви enum — метод `label()`; ціни — у гривнях: `$book->price->format()` (об’єкт-значення `Money`,
  у базі — копійки; для сирих сум зі звітів — `Money::fromCents($cents)->format()`).
- Слаги завжди ASCII: `Str::slug($value, '-', 'uk')` («Тіні забутих предків» → `tini-zabutykh-predkiv`).

## Карта уроків

| Етап | Тема | Конспект |
|---|---|---|
| 0 | Налаштування проєкту: Sail, Debugbar, суворий режим, черга в базі даних | [docs/00-setup.md](docs/00-setup.md) |
| 1 | Схема БД, моделі (PHP-атрибути), enum, фабрики, сидери | [docs/01-schema.md](docs/01-schema.md) |
| 2 | Зв’язки Eloquent, власна pivot-модель, мапа морфів, `/lab/relations` | [docs/02-relations.md](docs/02-relations.md) |
| 3 | Маршрути (прив’язки, scoped, withTrashed, групи, fallback), макети й компоненти Blade, каталог | [docs/03-routes-blade.md](docs/03-routes-blade.md) |
| 4 | N+1: суворий режим, профайлер, лабораторія з 19 варіантів, тести сталої кількості запитів | [docs/04-n-plus-one.md](docs/04-n-plus-one.md) |
| 5 | Колекції проти Query Builder, chunk/lazy/cursor, звіти, потокове CSV | [docs/05-collections.md](docs/05-collections.md) |
| 6 | Аксесори й мутатори, касти (`Money`, `encrypted`), серіалізація, локальні й глобальні скоупи, події та спостерігачі, масове присвоєння, `/lab/models` | [docs/06-attributes-casts.md](docs/06-attributes-casts.md) |
| 7 | _буде згодом_ | |
| 8 | _буде згодом_ | |
| 9 | _буде згодом_ | |
| 10 | _буде згодом_ | |

Нові уроки починайте з шаблону [docs/_template.md](docs/_template.md).
