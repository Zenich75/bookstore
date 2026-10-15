# 04 — The N+1 problem

## Goal

Recognise, measure and fix N+1 queries: see how Blade hides them, compare loading strategies
with real numbers, and guard every public page with a regression test so an N+1 can't come back.

Open **<http://localhost/lab/n-plus-one>** — 19 variants rendering the same card for the same 50
books; click a variant for its code, metrics, grouped duplicate queries and output.

## Where to look in code

- `app/Providers/AppServiceProvider.php` — `Model::handleLazyLoadingViolationUsing()`.
- `app/Lab/LazyLoading.php` — `allowDuring()` / `allowMissingAttributesDuring()`.
- `app/Lab/QueryProfiler.php`, `app/Lab/Profile.php` — query count, DB/wall time, peak memory,
  duplicate groups.
- `app/Lab/NPlusOne/NPlusOneLab.php` — all variants; `app/Lab/Models/LabBook.php` — accessor and
  `chaperone()` demos.
- `resources/views/lab/n-plus-one/_card.blade.php` — the shared card.
- `tests/Pest.php` → `expectConstantQueries()`; `tests/Feature/NPlusOne/*`.

## Key concepts

### What N+1 is, and why Blade hides it

```php
$books = Book::all();               // 1 query
@foreach ($books as $book)
    {{ $book->genre->name }}        // + 1 query per book  => N + 1
@endforeach
```

`$book->genre` reads like a property, so nothing in the template says "query". Each relation
used in a loop adds N queries; nested relations multiply (reviews → user → profile). The page
works and is fast with 5 rows in development, then slows down with 500 in production.

### Results (`SEED_SCALE=full`: 50k books, 200k reviews; 50-book sample; 2nd of 2 runs)

| Variant | Queries | DB ms | Wall ms | Memory |
|---|---:|---:|---:|---:|
| a. all lazy | 201 | 109.6 | 211.7 | 1.5 MB |
| b. all lazy, strict mode | 1 → `LazyLoadingViolationException` | 1.3 | 3.6 | 0.2 MB |
| c. `with(['authors', 'genre'])` | 103 | 56.5 | 122.9 | 1.3 MB |
| d. `with()` + cover + `withCount` | **4** | 7.9 | 36.0 | 0.9 MB |
| e. `->approvedReviews->count()` | 5 | 8.5 | 42.0 | 1.2 MB |
| f. feed, lazy `reviews.user.profile` | 375 | 174.5 | 343.3 | 2.1 MB |
| g. feed, `with('approvedReviews.user.profile')` | 7 | 9.8 | 62.3 | 1.8 MB |
| h. feed, latest 3 per book (eager limit) | 7 | 11.4 | 57.8 | 1.6 MB |
| i. `load()` / `loadMissing()` | 5 | 6.9 | 37.4 | 1.0 MB |
| j1. images, `morphTo` + lazy nested | 91 | 96.5 | 281.6 | 8.3 MB |
| j2. images, `morphWith()` | 5 | 26.8 | 182.8 | 8.1 MB |
| k1. over-eager (everything) | 12 | 17.3 | 91.8 | 3.0 MB |
| k2. only needed columns | **4** | 5.3 | 33.9 | 0.9 MB |
| k3. select without `genre_id` (broken) | 3 | 4.6 | 30.9 | 0.8 MB |
| l1. accessor reading a relation | 53 | 40.8 | 98.8 | 1.1 MB |
| l2. accessor + `with('authors')` | 4 | 6.3 | 36.1 | 1.0 MB |
| m1. inverse `$review->book` | 107 | 61.5 | 165.2 | 2.4 MB |
| m2. `->chaperone()` | 7 | 8.9 | 66.1 | 2.0 MB |
| n. `withRelationshipAutoloading()` | 5 | 8.2 | 40.3 | 1.4 MB |

The DB is local (Docker network). With a remote DB every query also pays network latency, so
the gap between 4 and 201 queries grows further.

### Fixes, ranked by impact

1. **Eager-load nested chains** — `with('approvedReviews.user.profile')`: 375 → 7 queries (f → g).
2. **Eager-load everything the view touches** — `with([...])` + `withCount()`: 201 → 4 (a → d).
   Half a fix is still an N+1: (c) loads two relations and keeps 100 lazy queries.
3. **Inverse relations** — `->chaperone()`: 107 → 7 (m1 → m2).
4. **Polymorphic nested relations** — `morphWith([Book::class => ['genre'], ...])`: 91 → 5 (j).
5. **Relations inside accessors** — eager-load what the accessor reads: 53 → 4 (l).
6. **Aggregate in SQL, not PHP** — `withCount()` instead of loading rows to `->count()` them:
   −25 % memory here (e vs d), far more with many reviews per book.
7. **Load less** — eager limit (h: only 3 reviews per book via `ROW_NUMBER() OVER (PARTITION BY
   book_id ORDER BY id DESC)`), narrow `select` (k2), and no unused relations (k1 → d: 12 → 4 queries,
   3.0 → 0.9 MB).

`load()`/`loadMissing()` (i) and automatic eager loading (n) reach the same result when you
don't control the original query.

### Pitfalls

- **`select` without foreign keys** (k3): eager loading matches parents and children by key. Leave
  out `books.genre_id` and `genre` is silently `null` for every book. For morph relations select
  `imageable_type` and `imageable_id`. Strict mode's `preventAccessingMissingAttributes` turns
  this into a `MissingAttributeException`; the lab turns that check off to show the silent version.
- **Over-eager loading** (k1): "just load everything" removes the N+1 but triples memory and runs
  queries for data the page never shows. Load what the view uses.
- **Accessors** (l): `$book->author_names` looks like a column but reads `$this->authors`. An
  accessor that touches a relation needs that relation eager-loaded wherever it is used in a list.
- **Inverse relations** (m): `$book->reviews` is loaded, then `$review->book` queries the book you
  already have. Use `->chaperone()` on the hasMany, or don't navigate back.
- **Polymorphic relations** (j): `with('imageable')` loads each type in one query, but nested
  relations differ per type. Use `morphWith()` (or `morphWithCount()`).
- **Strict mode only guards collections.** Eloquent sets the lazy-loading guard only on models
  fetched together with others (`Builder::hydrate`, `count > 1`). `Book::find($id)->authors` or a
  route-bound `$book` may lazy-load freely, but the models inside its collections are guarded.
  That's why `expectConstantQueries()` exists as a second check.
- **`allowDuring()` must wrap the query too**: the guard is copied onto each model when it is
  hydrated, not checked again later.

### Strict mode in development, logging in production

`Model::shouldBeStrict()` makes every lazy load in a loop throw, so an N+1 fails the request or
test that introduced it. In production a missing `with()` should not become a 500 error, so
`handleLazyLoadingViolationUsing()` throws only in `local`/`testing` and otherwise logs a warning
with model, relation and URL. You find it in the logs and fix it without an outage. The
framework's exemption for new models (`! exists || wasRecentlyCreated`) is kept.

### Regression tests

`expectConstantQueries($seed, $request)` seeds 5 and then 30 related records, each inside a
rolled-back transaction, and asserts the page runs the same number of queries. It covers home,
the catalog (with filters and rating sort), book, author and warehouse pages, the genre-tree view
composer, and the index pages. On failure it prints the extra queries.

### Audit of the public pages (stage 3)

Query counts were identical with `small` and `full` data on every page (home 10, catalog 9–11,
book 19, author 5, warehouse 7, genres/authors/warehouses 4). There was no N+1. Findings:

- **authors.show over-fetched**: it loaded `books.*` (incl. `meta` JSON, ISBN, price…) to print
  title, year and slug. Fixed: `select(['books.id', 'books.title', 'books.slug', 'books.published_year'])`.
- **book page**: 19 queries, all distinct and constant (`users` ×3 = latest/best/paginated
  reviews, `genres` ×2 = genre + parent). Acceptable.
- **Not N+1 but slow on full data**: catalog sorted by rating (~580 ms: `AVG()` subquery for
  every book), `warehouses.show` (~320 ms), `/warehouses` and `/genres` (115–225 ms aggregate
  subqueries). These are indexing/denormalization problems for stage 5.

### Not covered here

Queued jobs have their own N+1: `SerializesModels` re-fetches models when the job runs, without
the relations you loaded before dispatching (and `withoutRelations()` controls that). That is
part of stage 9 (queues).

## Exercise

1. Add the book's tags to `_card` and watch which variants break or grow. Fix (d) to stay at a
   constant query count.
2. Write an `expectConstantQueries` test for `admin.books.show`.
3. Make `Genre::books` lists on `/genres` show the first 3 titles per genre without N+1 (eager
   limit) and compare it with a lazy version in the profiler.
4. Turn on `Model::automaticallyEagerLoadRelationships()` globally in a branch, run the test suite
   and the lab — what changes, and what would you lose?
