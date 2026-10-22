# 08 — Форми, валідація, відгуки

## Мета

Винести валідацію з контролерів у **Form Request** і розібратися в його життєвому циклі; навчитися
правилам для масивів, умовних полів, власним правилам і перевіркам кількох полів одразу. Зібрати
форми з повторно використовуваних компонентів Blade (старі значення, помилки, доступність), побудувати
публічний потік відгуків із модерацією й побачити, чому `{{ }}` захищає від XSS, а `{!! !!}` — ні.

## Де дивитися в коді

- `app/Http/Requests/` — усі Form Request:
  `Auth/RegisterRequest`, `Auth/LoginRequest` (із обмеженням спроб), `Admin/StoreBookRequest`,
  `Admin/UpdateBookRequest`, `Manage/UpdateStockRequest`, `StoreReviewRequest`, `UpdateReviewRequest`,
  `CatalogFilterRequest`.
- `app/Rules/ValidIsbn13.php` — власне правило (контрольна цифра ISBN-13).
- `app/Http/Controllers/ReviewController.php` — відгуки читача, перехоплення
  `UniqueConstraintViolationException`; модерація — `Admin/ReviewController.php`.
- `app/Http/Controllers/Concerns/ShowsBook.php` — «мій відгук» в обхід глобального скоупу.
- `resources/views/components/form/*` — `input`, `textarea`, `select`, `radio-rating`, `error`.
- `resources/views/admin/books/form.blade.php`, `_author-row.blade.php` — повторювач авторів на
  чистому JS; `resources/views/books/partials/my-review.blade.php` — форма відгуку.
- `resources/views/errors/{419,422,429}.blade.php` — перекладені сторінки помилок.
- `app/Lab/XssLab.php`, `resources/views/lab/xss.blade.php` — лабораторія XSS (`/lab/xss`).
- `lang/{uk,en}/validation.php` — повідомлення власних правил, `custom`, назви атрибутів.
- `tests/Feature/Forms/*`, `tests/Feature/Reviews/ReviewFlowTest.php`, `tests/Feature/Lab/LabXssTest.php`,
  `tests/Feature/Web/{CatalogTest,ErrorPagesTest}.php`.

## Ключові поняття

### Життєвий цикл Form Request

Контролер лише оголошує тип параметра — `store(StoreBookRequest $request)`. Контейнер створює
запит і викликає `validateResolved()` **до** дії контролера:

```text
prepareForValidation()   нормалізація вводу (ISBN → цифри, «1 299,90» → «1299.90», слаг → ASCII)
        ↓
authorize()              false → 403 (AuthorizationException), правила навіть не запускаються
        ↓
rules() + after()        помилка → failedValidation() → redirect back з помилками й old input
        ↓
passedValidation()       хук після успішної перевірки
        ↓
дія контролера           $request->validated() / $request->safe()->only([...])
```

- `prepareForValidation()` змінює лише копію вводу в Form Request. Після невдалої валідації `old()`
  показує те, що користувач **набрав** (`978-617-…`), а не нормалізовані цифри.
- `authorize()` — через політики: `$this->user()->can('create', [Review::class, $this->book()])`.
  Для книг і залишків ту саму перевірку робить і маршрут (`#[Authorize]`, `can:update,stock`);
  повтор у запиті робить його безпечним, хоч би де його використали.
- `$stopOnFirstFailure = true` (`LoginRequest`): після першого невалідного поля інші не перевіряються.
- `$errorBag = 'reviewEdit'` (`UpdateReviewRequest`): помилки потрапляють у `$errors->reviewEdit`.
- `getRedirectUrl()` перевизначено для відгуків: назад до `#review-form`, а не на початок сторінки.

### Шпаргалка правил

| Що | Де | Приклад |
|---|---|---|
| Enum | книга, залишок, каталог | `Rule::enum(BookFormat::class)`, `->only(self::REASONS)` |
| Існує в БД | книга | `Rule::exists('authors', 'id')` |
| Існує з умовою | каталог | `Rule::exists('authors', 'slug')->where(fn ($q) => $q->whereExists(…author_book…))` |
| Унікальне, крім себе | книга | `Rule::unique('books', 'isbn')->ignore($this->book())` |
| Масиви | книга | `authors` → `array, max:10`; `authors.*.id` → `nullable, integer, distinct, exists`; `authors.*.role` → `required_with:authors.*.id, enum`; `authors.*.sort_order` |
| Умовно обов’язкове | залишок | `Rule::requiredIf(fn () => quantity > 0)` для `shelf_code` |
| Різні правила за умовою | залишок | `Rule::when($quantityChanges, ['required'], ['nullable'])` для `reason` |
| Лише якщо поле є | відгук (редагування), каталог | `sometimes` |
| Зупинитися на першій помилці поля | книга | `bail` перед `ValidIsbn13` і `unique` (без зайвого запиту) |
| Власне правило-клас | книга | `new ValidIsbn13` → `$fail('validation.isbn13_checksum')->translate()` |
| Правило-замикання | відгук | `function ($attribute, $value, $fail) { … }` — заборона посилань |
| Кілька полів разом | залишок | `after()`: `reserved <= quantity` |

**Пастка: `unique` і м’яке видалення.** Унікальний індекс у БД покриває **і** видалені в кошик
рядки. `Rule::unique('books', 'isbn')` типово їх теж бачить — і це правильно. Якщо додати
`->withoutTrashed()`, дублікат ISBN видаленої книги пройде валідацію й упаде на `INSERT` з 500.
Тест: `a soft-deleted book still owns its ISBN and slug`.

**Гроші без float.** Ціна приходить рядком (`199,90` або `199.90`), `prepareForValidation()` прибирає
пробіли й міняє кому на крапку, `regex` перевіряє формат, а `Money::fromUah()` розбирає рядок у копійки.
`(float) '199.90' * 100` дав би `19989.999…`.

### Використання результату: `validated()` проти `safe()`

- `$request->validated()` — лише поля, для яких є правила (`RegisterRequest` → `User::create()`).
- `$request->safe()->only([...])` / `->except([...])` — явний білий список поверх валідації.
- **Ніколи** `$request->all()` у `create()`/`update()`: у `Review` поле `is_approved` fillable (його
  ставить модератор), тож `create($request->all())` дозволив би читачеві самому схвалити свій відгук.
  Тест: `is_approved cannot be smuggled in`.

### Каталог: невалідні фільтри відкидаються

`CatalogFilterRequest` перевизначає `failedValidation()`: замість винятку запам’ятовує невдалі ключі,
а `filters()` повертає `Validator::valid()` лише для ключів із правилами. `validated()` тут не годиться —
він кидає виняток, доки є хоч одна помилка.

Компроміс: старі закладки й вручну змінені URL не ламають сторінку (без редіректу й 422), але
відвідувач не дізнається, що фільтр проігноровано. Для необов’язкових фільтрів це прийнятно; для
обов’язкового параметра потрібна видима помилка.

### Помилки й `old()` у компонентах

`<x-form.input name="authors[5][role]">` перетворює ім’я з дужками на ключ із крапками
(`authors.5.role`) — саме так його знають `old()` і `$errors`. Кожен компонент:

- бере значення з `old($key, $value)` (пароль ніколи не заповнюється повторно);
- показує помилку через `@error($key, $bag)` (компонент `<x-form.error>`, необов’язкова сумка);
- ставить `aria-invalid="true"` і `aria-describedby` на елемент із текстом помилки.

Повторювач авторів додає рядки з `<template>` з новим індексом і просто видаляє їх; індекси можуть
бути «дірявими» (`0, 3, 7`) — і `old('authors')`, і помилки `authors.7.id` їх зберігають.

Іменована сумка: форма редагування відгуку читає `$errors->reviewEdit` (у тестах —
`assertSessionHasErrorsIn('reviewEdit', [...])`), а `<details>` із формою відкривається, коли в цій
сумці є помилки.

### CSRF і методи

- `@csrf` додає прихований `_token`; без нього (або коли сесія протухла) — **419**, сторінка
  `errors/419.blade.php`. У тестах перевірку CSRF вимкнено фреймворком.
- HTML-форми вміють лише GET/POST: `@method('PUT')`/`@method('DELETE')` додає `_method`.
- Флеш-повідомлення: `@session('status') {{ $value }} @endsession`.

### Відгуки

| Хто | Що бачить на сторінці книги |
|---|---|
| Гість | «Увійдіть, щоб залишити відгук» → `books.reviews.create` за `auth` → після входу `intended()` повертає до форми |
| Непідтверджена пошта | прохання підтвердити; `verified` не пропустить POST |
| Читач без відгуку | форма: оцінка 1–5, текст 20–2000 символів без посилань |
| Автор відгуку | свій відгук зі статусом «на модерації» (редагувати/видалити) або «схвалено» (лише читання) |

- «Мій відгук» читається `Review::withoutGlobalScope(ApprovedScope::class)` **разом** з умовою
  `user_id = поточний користувач`: чужий відгук на модерації не бачить ніхто, крім адміністратора.
- `throttle:reviews` — 5 записів на хвилину на користувача; далі 429 (`errors/429.blade.php`).
- **Гонка.** Політика `create` перевіряє «відгуку ще немає», але два одночасні запити обидва пройдуть
  перевірку. Унікальний індекс `(user_id, book_id)` пропустить лише один `INSERT`; другий кидає
  `UniqueConstraintViolationException`, яку контролер перетворює на звичайну помилку валідації.
  Повторна (не одночасна) спроба отримує 403 від `authorize()`.
- Події/черги для відгуків — на етапі 9 (у `ReviewController::store()` залишено `TODO`).

### XSS: екранування, «сирий» вивід, markdown, схеми URL

```blade
{{ $body }}                     {{-- e(): < > & " ' → сутності. Типовий вибір --}}
{!! nl2br(e($body)) !!}         {{-- спершу екранувати, потім додати <br> — так виводимо відгуки --}}
{!! nl2br($body) !!}            {{-- ДІРА: nl2br() нічого не екранує, теги користувача живі --}}
{!! Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
<a href="{{ $url }}">           {{-- екрановано, але javascript:alert(1) лишається робочим посиланням --}}
```

Чому `{!! nl2br(e($body)) !!}` безпечно: після `e()` у рядку немає жодного `<`, тож єдині теги в
результаті — `<br />`, які додав `nl2br()`. У зворотному порядку `nl2br()` пропускає все, що ввів
користувач. `Str::markdown()` із типовими налаштуваннями теж небезпечний: GFM екранує `<script>`, але
пропускає `<img onerror=…>` і `javascript:`-посилання.

Екранування не змінює **схему** URL. Захист для полів-посилань — валідація з переліком дозволених схем:
`'website' => ['nullable', 'url:http,https']`. Лабораторія `/lab/xss` показує всі варіанти поруч, а
отриманий HTML виводить як екранований текст — нічого не виконується.

## Вправа

1. Додайте авторові поле «сайт» (`authors.website`, міграція + форма в адмінці) з правилом
   `url:http,https` і виведіть його посиланням на сторінці автора. Перевірте: тест-набір із
   `https://…` (проходить), `javascript:alert(1)` і `ftp://…` (відхилено).
2. Дозвольте менеджерові вказувати причину `receipt` лише коли кількість **збільшується** (у `after()`
   або через `Rule::when`). Перевірте: dataset у `tests/Feature/Forms/StockFormTest.php`.
