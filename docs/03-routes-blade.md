# 03 — Маршрути, контролери та Blade

## Мета

Побудувати публічний сайт — головну, каталог із фільтрами, сторінки книги, автора, жанру й
складу — і опанувати основні техніки маршрутизації (прив’язка за слагом, вкладені прив’язки
(scoped bindings), `withTrashed`, ресурсні й контролерні групи, резервний маршрут (fallback)) та
будівельні блоки Blade (макети-компоненти проти `@extends`, анонімні й класові компоненти, слоти,
властивості (props), набір атрибутів, компонувальники представлень (view composers)).

## Де дивитися в коді

- `routes/web.php` — кожна техніка маршрутизації з коротким коментарем.
- `app/Http/Controllers/*` — `HomeController` (invokable), `BookController` (запит каталогу
  будується через `when()`; `cardQuery()` — усе, що читає картка книги; з етапу 6 — скоуп `Book::withCardData()`), `AuthorController`
  (контролерна група + вкладена прив’язка), `Concerns/ShowsBook.php` (спільна сторінка книги), `Admin/*`.
- `resources/views/components/` — `layouts/app`, `book-card`, `rating-stars`, `card`,
  `genre-tree` (рекурсивний), `stock-badge` (представлення класового компонента).
- `app/View/Components/StockBadge.php` — класовий компонент із логікою статусу залишку.
- `app/View/Composers/GenreTreeComposer.php` + `AppServiceProvider` — компонувальник представлення.
- `resources/views/layouts/legacy.blade.php` + `about.blade.php` — порівняння з `@extends`.
- `lang/{uk,en}/ui.php` — усі тексти інтерфейсу; у шаблонах лише `__()` / `trans_choice()`.
- `tests/Feature/Web/*` — маршрути, фільтри/сортування/пагінація каталогу, `StockBadge`, локалізація.

## Ключові поняття

### Техніки маршрутизації

| Техніка | Де | Примітки |
|---|---|---|
| Invokable-контролер | `Route::get('/', HomeController::class)` | одна дія → `__invoke()` |
| Ресурс з обмеженням | `Route::resource('books', …)->only(['index', 'show'])` | імена `books.index`, `books.show` |
| Прив’язка за стовпцем | `->scoped(['book' => 'slug'])`, `{author:slug}`, `{genre:slug}` | `route('books.show', $book)` сам підставляє слаг |
| Прив’язка за id (типово) | `/warehouses/{warehouse}` (з етапу 7 — `/manage/warehouses/{warehouse}/stocks`), `/admin/books/{book}` | під капотом `Model::findOrFail($id)` |
| Контролерна група | `Route::controller(AuthorController::class)->group(…)` | дії вказуються як `'index'`, `'show'` |
| Вкладена прив’язка (scoped binding) | `/authors/{author:slug}/books/{book:slug}` + `->scopeBindings()` | дочірня модель шукається через `$author->books()` → 404, якщо не пов’язана |
| `withTrashed()` | `admin.books.show` | м’яко видалені моделі знаходяться; публічні маршрути повертають 404 |
| Групи з префіксом та іменем | `prefix('admin')->name('admin.')`, `prefix('lab')->name('lab.')` | проміжне ПЗ (middleware) для адмінки — етап 7, [07-middleware-auth.md](07-middleware-auth.md) |
| Маршрут-представлення | `Route::view('/about', 'about')` | контролер не потрібен |
| Перенаправлення | `genres.show` → `redirect()->route('books.index', ['genre' => …])` | «сторінка» жанру — це відфільтрований каталог |
| Резервний маршрут | `Route::fallback(…)` | останній маршрут; рендерить `errors/404` |

Перевіряйте командою `sail artisan route:list --except-vendor` (`-v` — показати middleware,
`--name=admin` — відфільтрувати). `resources/views/errors/404.blade.php` використовується і
резервним маршрутом, і самим Laravel для кожної помилки 404 (наприклад, коли не вдалася прив’язка моделі).

> Вкладена прив’язка потребує зв’язку в батьківській моделі, названого як дочірній параметр у
> множині (`books`), і вмикається автоматично, коли дочірній параметр має власний ключ
> (`{book:slug}`); `->scopeBindings()` робить це явним (і працює також для `{book}` за id).

### Запит каталогу

Фільтри читаються поблажливо через `$request->string()`, `->boolean()`, `->enum()` — невідомий
слаг жанру чи автора, формат або сортування просто ігнорується (валідація через Form Request —
етап 8). Запит будується через `->when($value, fn ($query, $value) => …)`, тож кожен фільтр — це
окремий необов’язковий крок. Через суворий режим дані для картки треба завантажити наперед, тому
`BookController::cardQuery()` жадібно завантажує `genre`, `authors`, `cover` і додає
`withCount/withAvg('approvedReviews')` та `withSum('stocks', 'quantity')`. На етапі 6 ці частини
стали локальними скоупами: `Book::withCardData()`, `ofGenre()`, `byAuthor()`, `inStock()`, `sorted()`
(див. [06-attributes-casts.md](06-attributes-casts.md)), а `cardQuery()` видалено. `paginate(24)->withQueryString()` зберігає фільтри в посиланнях пагінації.

### Макет-компонент проти `@extends`

| | `<x-layouts.app title="…">` (компонент) | `@extends('layouts.legacy')` (успадкування) |
|---|---|---|
| Вміст | типовий `{{ $slot }}` | `@section('content')` → `@yield('content')` |
| Дані | властивості (`@props(['title' => null])`) з типами й значеннями за замовчуванням | рядки `@section('title', '…')` |
| Компонування | макети — це звичайні компоненти: можна вкладати, передавати іменовані слоти | один ланцюжок успадкування |
| Де використовується | усі сторінки | лише `/about` |

### Компоненти

- **Анонімні** (`resources/views/components/*.blade.php`): просто представлення. `@props([...])`
  оголошує властивості (зі значеннями за замовчуванням); усе інше потрапляє в `$attributes`.
- **Набір атрибутів**: `{{ $attributes->merge(['class' => '…']) }}` додає класи, передані
  викликачем; `$attributes->class([...])` — умовні класи. `<x-book-card class="…" data-x="…">`.
- **`@class([...])`**: умовні CSS-класи на будь-якому елементі.
- **Іменовані слоти**: `<x-slot:header>` / `<x-slot:footer>` у `x-card`; перевірка — `@isset($header)`.
  Слоти мають власні атрибути (`$header->attributes`).
- **Рекурсивний компонент**: `x-genre-tree` рендерить `<x-genre-tree>` для піджанрів — лише коли
  `relationLoaded('children')`, інакше суворий режим кинув би виняток лінивого завантаження.
- **Класовий компонент** (`StockBadge`): аргументи конструктора = властивості, публічні методи
  доступні в представленні (`$state()`, `$label()`). Обирайте його, коли є справжня логіка, яку
  варто тестувати без HTML.
- **Компонувальник представлення**: `View::composer('books.partials.sidebar', GenreTreeComposer::class)`
  додає `$genreTree` щоразу, коли рендериться цей partial — контролер про це не знає.

### Інші використані директиви

`@forelse/@empty` (порожні стани), `$loop->first/last/iteration`, `@include` (бачить змінні
батьківського представлення) проти `@each` (ізольований partial для кожного елемента + представлення
для порожнього списку), `@isset`, `@env('local')`, `@once` + `@push('scripts')` → `@stack('scripts')`,
`@selected`, `@checked`, `@unless`.

### Тексти, числа, дати

- Тексти — лише через `__('ui.…')`; кількості — `trans_choice('ui.counts.books', $n)` з трьома
  українськими формами («1 книга / 2 книги / 5 книг»).
- Числа — `Number::format()` (локаль застосунку: «1 234,5»), ціни —
  `Number::currency($price / 100, in: 'UAH', locale: 'uk')` → «1 234,50 ₴».
- Дати — Carbon із локаллю `uk`: `translatedFormat('j F Y')` → «15 жовтня 2026»,
  `diffForHumans()` → «3 години тому».

### Екранування

`{{ $value }}` виконує `htmlspecialchars()`: тексти відгуків і назви — це дані від користувачів,
і вони завжди виводяться екранованими. `{!! $value !!}` виводить сирий HTML — ніколи не
застосовуйте його до введення користувача (у проєкті так виводяться лише тексти з наших власних
lang-файлів). Демонстрація XSS — частина етапу 8.

## Вправа

1. Додайте `GET /tags/{tag:slug}`, що показує книги з тегом (перевикористайте `cardQuery()`, з етапу 6 — `Book::withCardData()`), а
   також фільтр «тег» і відповідний чип у каталозі.
2. Перетворіть `x-rating-stars` на класовий компонент і покрийте юніт-тестом округлення.
3. Замініть два маршрути на `Route::resource('warehouses', …)->only(['index', 'show'])` — що
   зміниться в `route:list`?
4. Приберіть `->withQueryString()` і подивіться, як фільтри зникають на другій сторінці.
