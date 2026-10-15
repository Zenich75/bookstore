# 05 — Collections vs Query Builder

## Goal

Know where work should happen — in the database or in PHP — and what each choice costs.
Compare return types, Eloquent Collection methods, relation queries vs loaded relations,
aggregates, iteration strategies for large tables, mass updates and subqueries, all measured on
the full dataset. Build two reports with the Query Builder and stream a CSV without loading it
into memory.

Open **<http://localhost/lab/collections>** (one tab per section) and **<http://localhost/reports/stock>**.

## Where to look in code

- `app/Lab/CollectionExamples.php` — every comparison (each approach is the code shown on the page).
- `app/Lab/Collections/{Comparison,Approach}.php`, `app/Lab/Presenter.php`, `app/Lab/QueryProfiler.php`.
- `app/Reports/StockByWarehouseGenreReport.php`, `app/Reports/TopAuthorsReport.php`.
- `app/Http/Controllers/ReportController.php` — invokable, `->defaults('report', …)`, CSV `streamDownload`.
- `tests/Feature/Reports/*`, `tests/Feature/Lab/LabCollectionsTest.php`.

## Key concepts

### Do it in SQL or in PHP?

| Task | In SQL (Query Builder / Eloquent query) | In PHP (Collection) |
|---|---|---|
| Filter, sort, paginate many rows | ✅ `where`, `orderBy`, indexes, `LIMIT` | only for rows already in memory |
| Count / sum / avg / max | ✅ `count()`, `withCount`, `selectRaw('sum(...)')` | when the rows are loaded anyway |
| Group + aggregate (reports) | ✅ `GROUP BY` returns a few rows | ❌ hydrates every row first |
| "Does any exist?" | ✅ `exists()` | ❌ |
| A few columns | ✅ `pluck()`, `select([...])` | ❌ `get()->pluck()` loads whole models |
| Business logic per model (events, casts, accessors) | ❌ | ✅ models |
| Combining data from different sources / small lists | — | ✅ `map`, `groupBy`, `keyBy` |
| Already-loaded relation reused several times | — | ✅ filter the loaded collection |

Rule of thumb: send **questions** to the database (*how many, which ones, what total*), bring
back **only the rows you'll render**, and use collections to shape what you already have.

### Base vs Eloquent Collection; hydration cost

`DB::table()->get()` and `Model::toBase()->get()` return an `Illuminate\Support\Collection` of
`stdClass` rows with raw values: `format` is the string `'paperback'`, `price` an int of cents,
`meta` a JSON string, `published_at` a string. `Book::get()` returns an
`Illuminate\Database\Eloquent\Collection` of models: enum/Carbon/array casts, relation methods
(none loaded until you ask), `modelKeys()`, `load*()`, `find()`, `toQuery()`, `fresh()`. Eloquent
compares models **by key** (`diff`, `intersect`, `unique`, `contains`); a base collection
compares **by value**, so a changed attribute or another instance of the same row counts as
different. `toBase()` keeps the Eloquent query (soft-delete scope included) but skips hydration;
`DB::table('books')` doesn't know about soft deletes.

`map()` keeps an Eloquent Collection only while it returns models; returning strings/arrays
gives a base collection (no more `load()`/`toQuery()`).

Hydration cost, all 50,000 books (single cold run):

| | Queries | DB ms | Wall ms | Peak memory |
|---|---:|---:|---:|---:|
| `DB::table('books')->get()` | 1 | 173 | 179 | 67 MB |
| `Book::toBase()->get()` | 1 | 185 | 197 | 67 MB |
| `Book::get()` | 1 | 159 | **1,747** | **125 MB** |

The query costs the same; ~1.5 s and ~58 MB are spent building 50k models.

### Aggregates and pluck: SQL vs PHP (50k books)

| Task | SQL | Collection |
|---|---|---|
| avg/sum/max price | 13 ms, 72 KB | 2,763 ms, 89 MB |
| count by format | 52 ms, 43 KB | 1,949 ms, 90 MB |
| title by id | `pluck()` 48 ms, 26 MB | `get()->pluck()` 2,683 ms, 124 MB |

### Iterating large tables (sum the price of all ebooks, 50k books)

| Approach | Queries | DB ms | Wall ms | Peak memory |
|---|---:|---:|---:|---:|
| `get()` | 1 | 163 | 2,621 | 124.8 MB |
| `chunk(1000)` | 51 | 1,447 | 3,964 | 2.5 MB |
| `chunkById(1000)` | 51 | 292 | 2,770 | 2.5 MB |
| `lazy(1000)` | 51 | 1,428 | 3,472 | 4.9 MB |
| `lazyById(1000)` | 51 | 289 | 2,303 | 4.9 MB |
| `cursor()` | 1 | 99 | 5,436 | 10.5 MB |
| `cursor()->filter->map->take(10)` (first 10 only) | 1 | 71 | 77 | 10.5 MB |

What the numbers say (as measured, not as expected):

- All chunked approaches cut memory by ~25–50× vs `get()`; total time is similar because
  hydration dominates.
- `chunk`/`lazy` use `LIMIT/OFFSET`: the database re-reads all skipped rows for every page, so DB
  time is ~5× that of `chunkById`/`lazyById` (`WHERE id > ? LIMIT 1000`), and it grows
  quadratically with table size.
- `cursor()` runs one query and hydrates one model at a time, but it was the **slowest** here and
  used **more** memory than chunking: with MySQL's default *buffered* queries the driver receives
  the whole result set into PHP memory before yielding the first row (10.5 MB for raw rows), so
  only the hydration is spread out. Unbuffered queries (`PDO::MYSQL_ATTR_USE_BUFFERED_QUERY =>
  false`) would avoid that, at the cost of blocking the connection while iterating.
- `cursor()` **can't eager-load**: eager loading needs the set of parent keys of a page; cursor
  never has a page, so every relation access is a lazy load (an N+1 — strict mode throws). Use
  `lazyById()->…` with `with()` (it eager-loads per chunk) when you need relations.
- A `LazyCollection` pipeline stops early (`take(10)`: 77 ms), but the query has already
  transferred every row — put the filter in SQL when you can.

### The chunk pitfall

`chunk()` pages by `OFFSET`. If the callback changes the column you filter on, rows move out of
the result set and the next page starts too far:

| Approve 40,192 unapproved reviews (rolled back) | Chunks | Left unapproved | Queries | Wall ms |
|---|---:|---:|---:|---:|
| `where('is_approved', false)->chunk(100, …)` | 201 | **20,092** | 405 | 9,559 |
| `where('is_approved', false)->chunkById(100, …)` | 402 | **0** | 806 | 3,569 |

Half the rows are silently skipped. `chunkById()` (and `lazyById()`) page by `id > last id`,
which is stable under updates and also faster.

### Mass update vs model save (200 books, rolled back)

| | Queries | Wall ms | `updated_at` changed |
|---|---:|---:|---:|
| `$books->toQuery()->increment('price', 100)` | 3 | 56 | 200 |
| `DB::table('books')->whereIn(...)->increment(...)` | 3 | 46 | 0 |
| `$books->each(fn ($b) => $b->save())` | 202 | 241 | 200 |

(3 = fetch + update + verification read.) The Eloquent Builder adds `updated_at` to every
update; the Query Builder doesn't. Neither fires model events — only `save()` does, one UPDATE
per model. Observers and events are part of stage 6.

### Subqueries

`addSelect(['latest_review_at' => Review::select('created_at')->whereColumn('reviews.book_id',
'books.id')->latest()->limit(1)])` adds a column in one query (4 ms, 73 KB for 20 books) vs eager
loading `latestReview` (2 queries, 10 ms, 130 KB) — use it when you need a value, the relation
when you need the model. `whereHas()` compiles to the same `EXISTS (select * …)` subquery as a
hand-written `whereExists()` (both ~220 ms on 50k books); write `whereExists()` only when there is
no relation to use.

### Reports with the Query Builder

Reports aggregate many rows into a few, mix tables freely and don't need models, events or casts:
the Query Builder is the right tool. The lab compares the stock report done both ways (first
warehouse only — 25k stock rows — because the PHP way over all 125k rows took **13.1 s and
319 MB**):

| Stock by warehouse × root genre (25k rows) | Queries | Wall ms | Peak memory |
|---|---:|---:|---:|
| SQL (`StockByWarehouseGenreReport`) | 1 | 133 | 60 KB |
| PHP (load models + `groupBy`/`sum`) | 4 | 3,858 | 92.9 MB |

Both produce identical numbers (a test checks this on seeded data).

Performance work on the full dataset (MySQL 8.4):

| Report | First version | Final | Change |
|---|---:|---:|---|
| Stock report | 1.0 s | 0.3 s | aggregate on ids in a subquery, join names to the ~55 result rows afterwards |
| Top authors | 5.5 s | 0.6 s | pre-aggregate approved reviews per book (`joinSub`), then sum per author — instead of joining 200k reviews to every credit |
| CSV, 125k rows | 25 s | 0.9 s | `useIndex('primary')`: MySQL misestimated `books.deleted_at is null`, drove each chunk from `books` and filesorted (190 ms → 2 ms per chunk) |

Always `EXPLAIN` a report query on realistic data — the same SQL that is instant on 500 rows
can choose a terrible plan on 50k.

### Raw expression safety

`selectRaw`, `whereRaw`, `havingRaw`, `orderByRaw`, `groupByRaw` and `DB::raw()` insert their
string into SQL **as is**. Only fixed expressions go there (`coalesce(genres.parent_id,
genres.id)`); every value goes through bindings: `havingRaw('sum(reviews_count) >= ?',
[$this->minReviews])`, `where('stocks.warehouse_id', $id)`. Never interpolate input:
`havingRaw("count(*) >= $min")` is an SQL injection. Column/alias names can't be bound —
whitelist them (stage 8). The CSV export also prefixes cells starting with `= + - @` to stop
spreadsheet formula injection.

### Streaming CSV

`response()->streamDownload()` writes rows to `php://output` while `lazyById(1000)` reads them,
so memory stays flat: 125,219 lines / 9.6 MB in 0.9 s.

## Exercise

1. Add a `warehouse` filter to `/reports/stock` (bound parameter, validated later in stage 8)
   and a link from each warehouse card to its CSV.
2. Rewrite the large-volume test with `lazyById(1000)->each(...)` plus `with('genre')` and
   compare queries with `cursor()` accessing `->genre` (strict mode will tell you why).
3. Replace `chunk()` in the pitfall with `->each()` on `lazy()` — does it skip rows too? Why?
4. `EXPLAIN` the top-authors query before and after the `joinSub` refactoring and write down
   the access types.
