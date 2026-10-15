# 02 — Eloquent relationships

## Goal

Model every relation of the warehouse domain in Eloquent: one-to-one, one-to-many, many-to-many
(with plain and custom pivots), "has-one-of-many", "through" relations and polymorphic ones.
Learn to query, aggregate and write them — always loading relations explicitly, because strict
mode forbids lazy loading.

Open **<http://localhost/lab/relations>**: every example shows its code, result and the exact SQL.

## Where to look in code

- `app/Models/*` — relationship methods with return types and `@return` generics.
- `app/Models/Stock.php` — custom pivot (`extends Pivot`) with its own id and an accessor.
- `app/Providers/AppServiceProvider.php` — `Relation::enforceMorphMap()`.
- `database/migrations/2026_10_16_100000_use_morph_aliases_in_polymorphic_columns.php` — data
  migration from class names to aliases.
- `database/factories/BookFactory.php` — `withReviews()`, `withAuthors()`, `inWarehouses()`;
  `AuthorFactory::withBooks()` (`has()` / `hasAttached()`).
- `app/Lab/RelationExamples.php`, `app/Lab/Samples.php` — the lab registry and sample records.
- `tests/Feature/Relations/*`, `tests/Feature/Lab/LabRelationsTest.php`.

## Key concepts

### Relation types in this project

| Type | Example | Generated SQL (short) |
|---|---|---|
| `hasOne` | `User::profile()` | `select * from profiles where user_id in (…)` |
| `belongsTo` | `Review::book()`, `User::warehouse()`, `Genre::parent()` | `select * from books where id in (…)` |
| `hasMany` | `User::reviews()`, `Genre::children()`, `Book::stocks()` | `select * from reviews where user_id in (…)` |
| `hasMany` + `where` | `Book::approvedReviews()` | `… where book_id in (…) and is_approved = 1` |
| `hasOne` + `where` | `Warehouse::manager()` | `select * from users where role = 'manager' and warehouse_id in (…)` |
| `hasOne()->latestOfMany()` | `Book::latestReview()` | `inner join (select max(id) … group by book_id)` |
| `hasOne()->ofMany('rating','max')` | `Book::bestReview()` | nested `max(rating)` then `max(id)` subqueries joined back |
| `hasOneThrough` | `Warehouse::managerProfile()` | `select profiles.*, users.warehouse_id as laravel_through_key from profiles inner join users on users.id = profiles.user_id where users.warehouse_id in (…)` |
| `hasManyThrough` | `Genre::reviews()` | `select reviews.* … inner join books on books.id = reviews.book_id where books.genre_id = ? and books.deleted_at is null` |
| `belongsToMany` | `Book::genres()` / `Genre::secondaryBooks()` | `select genres.*, book_genre.book_id as pivot_book_id … inner join book_genre …` |
| `belongsToMany` + `withPivot`/`as` | `Book::authors()` / `Author::books()` | `… author_book.role as pivot_role, author_book.sort_order as pivot_sort_order … order by author_book.sort_order` |
| `belongsToMany` + `wherePivot` | `Book::translators()`, `Author::translatedBooks()` | `… and author_book.role = 'translator'` |
| `belongsToMany` + `using()` | `Warehouse::books()` / `Book::warehouses()` | `… stocks.id as pivot_id, stocks.quantity as pivot_quantity …` |
| `morphOne` (+ `where`) | `Book::cover()`, `Author::photo()` | `select * from images where imageable_type = 'book' and imageable_id in (…) and type = 'cover'` |
| `morphMany` | `Book::images()`, `Author::images()` | `… where imageable_type = 'book' and imageable_id in (…)` |
| `morphTo` | `Image::imageable()` | one query per type: `select * from authors where id in (…)` |
| `morphToMany` | `Book::tags()`, `Author::tags()` | `… inner join taggables … where taggable_type = 'book'` |
| `morphedByMany` | `Tag::books()`, `Tag::authors()` | `… inner join taggables on books.id = taggables.taggable_id where taggable_type = 'book'` |

Querying (lab section "Querying relations"): `has`, `whereHas`, `doesntHave`, `whereRelation`
add `exists (select …)` subqueries; `withCount`, `withAvg('reviews', 'rating')`,
`withSum('stocks', 'quantity')`, `withExists('cover')` add correlated subselects as
`reviews_count`, `reviews_avg_rating`, `stocks_sum_quantity`, `cover_exists`.

Writing (lab section "Writing relations", rolled back): `attach`, `detach`, `sync`,
`syncWithoutDetaching`, `toggle` (the last three return `attached/detached/updated` ids),
`updateExistingPivot`, `associate`/`dissociate` (belongsTo — remember to `save()`),
`saveMany`/`createMany` (hasMany/morphMany).

### Strict mode and loading relations

With `Model::shouldBeStrict()` `$book->authors` on a book that wasn't loaded with `authors`
throws `LazyLoadingViolationException`. Load explicitly:

```php
Book::with('authors')->find($id);              // eager, 2 queries for any number of books
$book->load('authors');                        // eager, after the fact
$book->authors()->where(...)->get();           // relation *method* = a query builder, always fine
```

### Pitfalls

**`hasOne` when there are many rows.** `hasOne(Review::class)` on a book with 10 reviews returns
an arbitrary one (whatever the DB returns first). Use "one of many":
`->latestOfMany()`, `->oldestOfMany()` or `->ofMany('rating', 'max')`. Ties on the aggregate
column are broken by `MAX(id)`, so the result is deterministic.

**Key order in `hasOneThrough` / `hasManyThrough`.**

```php
$this->hasManyThrough(
    Review::class,  // final
    Book::class,    // intermediate
    'genre_id',     // FK on the intermediate table (books) pointing to this model
    'book_id',      // FK on the final table (reviews) pointing to the intermediate
    'id',           // local key on this model (genres)
    'id',           // local key on the intermediate model (books)
);
```

Swapping the 3rd and 4th arguments is the classic mistake: the SQL is valid but joins the
wrong columns and returns nothing (or garbage). Soft deletes on the intermediate model are
applied automatically (`books.deleted_at is null`).

**Pivot columns are not loaded by default.** Without `->withPivot([...])` only the two keys are
selected, so `$author->credit->role` is `null` — or, with strict mode on, throws
`MissingAttributeException`; without `->withTimestamps()` `attach()` leaves
`created_at/updated_at` empty. Both must be declared on *each side* of the relation
(`Book::authors()` and `Author::books()`).

**Custom Pivot and `$incrementing`.** `Pivot` sets `$incrementing = false` because classic
pivots have a composite key. `stocks` has its own auto-increment `id`, so `Stock` declares
`#[Table(name: 'stocks', incrementing: true)]` (legacy: `public $incrementing = true;`).
Also add `'id'` to `withPivot()` to get it on `$book->stock`. Use `->using(Stock::class)` on
both sides, and `->as('stock')` for a readable accessor instead of `->pivot`.

**Morph map.** Without a map Eloquent stores the class name (`App\Models\Book`) in
`imageable_type`. That couples data to code: renaming/moving a model silently orphans rows.
`Relation::morphMap([...])` stores short aliases (`book`), but classes *not* in the map still
fall back to class names — inconsistent data. `Relation::enforceMorphMap([...])` stores aliases
**and throws `ClassMorphViolationException`** for any unmapped model, so forgetting to register
a new morphable model fails loudly in tests instead of writing class names to production data.
Existing rows are converted by a data migration (`up()` FQCN → alias, `down()` back); it uses
plain strings, not `Book::class`, so it stays valid if models change.

**Constrained relations and writes.** `Book::cover()` adds `where type = 'cover'`, but
`$book->cover()->create([...])` does *not* set `type` for you — pass it explicitly.

### Naming conventions

- `hasOne`/`belongsTo`/`morphOne`/`morphTo` → singular (`profile`, `genre`, `cover`, `imageable`);
  `hasMany`/`belongsToMany`/`morph*Many` → plural (`reviews`, `authors`, `tags`).
- Foreign key = `{singular_model}_id` (`genre_id`); pivot table = both singular names in
  alphabetical order (`author_book`, `book_genre`) — then no extra arguments are needed.
- Self-references and non-default names need explicit keys: `belongsTo(Genre::class, 'parent_id')`.
- Constrained variants are named by meaning: `approvedReviews`, `translators`, `latestReview`.
- Morph columns: `{name}able_type` / `{name}able_id` (`imageable`, `taggable`).

## Exercise

1. Add `Author::coAuthors()` — other authors the author has written books with. Which relation
   type fits? (Hint: there is no single built-in one; think about a query via `books`.)
2. Add `Book::lowestReview()` with `ofMany` and a test with tied ratings.
3. Make `Warehouse` morphable for images (a photo of the building): what must change in
   `enforceMorphMap`, and what happens if you forget? Write the failing test first.
4. In the lab, compare the SQL of `Book::has('reviews', '>=', 8)` and
   `Book::withCount('reviews')->having('reviews_count', '>=', 8)`.
