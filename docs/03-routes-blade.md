# 03 — Routes, controllers & Blade

## Goal

Build the public site — home, catalog with filters, book/author/genre/warehouse pages — and
learn the main routing techniques (binding by slug, scoped bindings, `withTrashed`, resource and
controller groups, fallback) and Blade's building blocks (component layouts vs `@extends`,
anonymous and class components, slots, props, the attribute bag, view composers).

## Where to look in code

- `routes/web.php` — every routing technique, each with a one-line comment.
- `app/Http/Controllers/*` — `HomeController` (invokable), `BookController` (catalog query built
  with `when()`; `cardQuery()` = everything a book card reads), `AuthorController` (controller
  group + scoped binding), `Concerns/ShowsBook.php` (shared book page), `Admin/*`.
- `resources/views/components/` — `layouts/app`, `book-card`, `rating-stars`, `card`,
  `genre-tree` (recursive), `stock-badge` (view of the class component).
- `app/View/Components/StockBadge.php` — class component with the stock-state logic.
- `app/View/Composers/GenreTreeComposer.php` + `AppServiceProvider` — view composer.
- `resources/views/layouts/legacy.blade.php` + `about.blade.php` — `@extends` comparison.
- `tests/Feature/Web/*` — routes, catalog filters/sorting/pagination, `StockBadge`.

## Key concepts

### Routing techniques

| Technique | Where | Notes |
|---|---|---|
| Invokable controller | `Route::get('/', HomeController::class)` | one action → `__invoke()` |
| Resource, limited | `Route::resource('books', …)->only(['index', 'show'])` | names `books.index`, `books.show` |
| Binding by column | `->scoped(['book' => 'slug'])`, `{author:slug}`, `{genre:slug}` | `route('books.show', $book)` fills the slug automatically |
| Binding by id (default) | `/warehouses/{warehouse}`, `/admin/books/{book}` | `Model::findOrFail($id)` under the hood |
| Controller group | `Route::controller(AuthorController::class)->group(…)` | actions referenced as `'index'`, `'show'` |
| Scoped binding | `/authors/{author:slug}/books/{book:slug}` + `->scopeBindings()` | child resolved via `$author->books()` → 404 if not related |
| `withTrashed()` | `admin.books.show` | soft-deleted models resolve; public routes return 404 |
| Prefix + name groups | `prefix('admin')->name('admin.')`, `prefix('lab')->name('lab.')` | middleware for admin comes in stage 7 |
| View route | `Route::view('/about', 'about')` | no controller needed |
| Redirect | `genres.show` → `redirect()->route('books.index', ['genre' => …])` | a genre "page" is the filtered catalog |
| Fallback | `Route::fallback(…)` | last route; renders `errors/404` |

Inspect with `sail artisan route:list --except-vendor` (add `-v` to see middleware,
`--name=admin` to filter). `resources/views/errors/404.blade.php` is used both by the fallback
and by Laravel for every 404 (e.g. a failed model binding).

> Scoped binding needs a relationship on the parent named like the child parameter in plural
> (`books`), and it applies automatically when the child uses a custom key (`{book:slug}`);
> `->scopeBindings()` makes it explicit (and works for `{book}` by id too).

### The catalog query

Filters are read leniently with `$request->string()`, `->boolean()`, `->enum()` — an unknown
genre/author slug, format or sort is simply ignored (validation with Form Requests: stage 8).
The query is built with `->when($value, fn ($query, $value) => …)` so that each filter is a
single, optional step. Strict mode means the card's data must be loaded up front, so
`BookController::cardQuery()` eager-loads `genre`, `authors`, `cover` and adds
`withCount/withAvg('approvedReviews')` and `withSum('stocks', 'quantity')`. In stage 6 these
become local scopes. `paginate(24)->withQueryString()` keeps filters in page links.

### Component layout vs `@extends`

| | `<x-layouts.app title="…">` (component) | `@extends('layouts.legacy')` (inheritance) |
|---|---|---|
| Content | default `{{ $slot }}` | `@section('content')` → `@yield('content')` |
| Data in | props (`@props(['title' => null])`), typed and with defaults | `@section('title', '…')` strings |
| Composability | layouts are just components: can be nested, take named slots | single inheritance chain |
| Used by | every page | only `/about` |

### Components

- **Anonymous** (`resources/views/components/*.blade.php`): just a view. `@props([...])` declares
  props (with defaults); everything else lands in `$attributes`.
- **Attribute bag**: `{{ $attributes->merge(['class' => '…']) }}` appends the caller's classes;
  `$attributes->class([...])` adds conditional classes. `<x-book-card class="…" data-x="…">`.
- **`@class([...])`**: conditional CSS classes on any element.
- **Named slots**: `<x-slot:header>` / `<x-slot:footer>` in `x-card`; check with `@isset($header)`.
  Slots have their own attributes (`$header->attributes`).
- **Recursive component**: `x-genre-tree` renders `<x-genre-tree>` for children — only when
  `relationLoaded('children')`, otherwise strict mode would throw on lazy loading.
- **Class component** (`StockBadge`): constructor args = props, public methods are callable in
  the view (`$state()`, `$label()`). Use it when there is real logic to test without HTML.
- **View composer**: `View::composer('books.partials.sidebar', GenreTreeComposer::class)` adds
  `$genreTree` whenever that partial renders — the controller doesn't know about it.

### Other directives used

`@forelse/@empty` (empty states), `$loop->first/last/iteration`, `@include` (shares parent
variables) vs `@each` (isolated partial per item + empty view), `@isset`, `@env('local')`,
`@once` + `@push('scripts')` → `@stack('scripts')`, `@selected`, `@checked`, `@unless`.

### Escaping

`{{ $value }}` runs `htmlspecialchars()`: review bodies and titles are user data and are always
printed escaped. `{!! $value !!}` prints raw HTML — never use it with user input. The XSS demo is
part of stage 8.

## Exercise

1. Add `GET /tags/{tag:slug}` that lists books with the tag (reuse `cardQuery()`), and a
   "tag" filter + chip in the catalog.
2. Turn `x-rating-stars` into a class component and unit-test the rounding.
3. Add `Route::resource('warehouses', …)->only(['index', 'show'])` instead of the two routes —
   what changes in `route:list`?
4. Remove `->withQueryString()` and watch the filters disappear on page 2.
