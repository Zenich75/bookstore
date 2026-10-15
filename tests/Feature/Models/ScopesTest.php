<?php

use App\Enums\BookFormat;
use App\Lab\Models\LabBook;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Builder;

test('published: has a date that is not in the future', function () {
    $past = Book::factory()->published()->create();
    Book::factory()->unpublished()->create();
    Book::factory()->create(['published_at' => now()->addMonth()]);

    expect(Book::published()->pluck('id')->all())->toBe([$past->id]);
});

test('inStock: some warehouse has quantity above reserved', function () {
    $warehouse = Warehouse::factory()->create();
    $available = Book::factory()->create();
    $reserved = Book::factory()->create();
    Book::factory()->create();

    $available->warehouses()->attach($warehouse, ['quantity' => 3, 'reserved' => 1]);
    $reserved->warehouses()->attach($warehouse, ['quantity' => 2, 'reserved' => 2]);

    expect(Book::inStock()->pluck('id')->all())->toBe([$available->id]);
});

test('ofGenre: main or secondary genre, children optional, model or slug', function () {
    $root = Genre::factory()->create(['slug' => 'fantasy']);
    $child = Genre::factory()->child($root)->create();
    $other = Genre::factory()->create();

    $rootBook = Book::factory()->for($root)->create();
    $childBook = Book::factory()->for($child)->create();
    $secondary = Book::factory()->for($other)->create();
    $secondary->genres()->attach($child);
    Book::factory()->for($other)->create();

    $ids = fn (Builder $query) => $query->orderBy('id')->pluck('id')->all();

    expect($ids(Book::ofGenre('fantasy')))->toBe([$rootBook->id, $childBook->id, $secondary->id])
        ->and($ids(Book::ofGenre($root)))->toBe([$rootBook->id, $childBook->id, $secondary->id])
        ->and($ids(Book::ofGenre($root, withChildren: false)))->toBe([$rootBook->id])
        ->and($ids(Book::ofGenre($child)))->toBe([$childBook->id, $secondary->id])
        ->and($ids(Book::ofGenre('no-such-genre')))->toBe([]);
});

test('byAuthor: model or slug, any role', function () {
    $author = Author::factory()->create(['slug' => 'lesia-ukrainka']);
    $written = Book::factory()->create();
    $translated = Book::factory()->create();
    Book::factory()->create();

    $written->authors()->attach($author, ['role' => 'author']);
    $translated->authors()->attach($author, ['role' => 'translator']);

    expect(Book::byAuthor('lesia-ukrainka')->orderBy('id')->pluck('id')->all())->toBe([$written->id, $translated->id])
        ->and(Book::byAuthor($author)->count())->toBe(2);
});

test('search: title fragment or digits-only ISBN prefix', function () {
    $kobzar = Book::factory()->create(['title' => 'Кобзар', 'isbn' => '9786170000017']);
    $other = Book::factory()->create(['title' => 'Лісова пісня', 'isbn' => '9789660000025']);

    expect(Book::search('обза')->pluck('id')->all())->toBe([$kobzar->id])
        ->and(Book::search('978-617')->pluck('id')->all())->toBe([$kobzar->id])
        ->and(Book::search('978 966 0000 02 5')->pluck('id')->all())->toBe([$other->id])
        ->and(Book::search('   ')->count())->toBe(2)
        // Letters + digits is not an ISBN search: no match on isbn.
        ->and(Book::search('isbn 978')->count())->toBe(0);
});

test('sorted: price, newest, rating with unknown keys falling back to newest', function () {
    $old = Book::factory()->create(['price' => 300, 'published_at' => now()->subYears(5)]);
    $new = Book::factory()->create(['price' => 100, 'published_at' => now()->subYear()]);
    $none = Book::factory()->unpublished()->create(['price' => 200]);
    Review::factory()->approved()->for($old)->create(['rating' => 5]);

    $ids = fn (string $key) => Book::withCardData()->sorted($key)->orderBy('id')->pluck('id')->all();

    expect($ids('price_asc'))->toBe([$new->id, $none->id, $old->id])
        ->and($ids('price_desc'))->toBe([$old->id, $none->id, $new->id])
        ->and($ids('newest'))->toBe([$new->id, $old->id, $none->id])
        ->and($ids('bogus'))->toBe($ids('newest'))
        ->and($ids('rating'))->toBe([$old->id, $new->id, $none->id]);
});

test('legacy scopeX syntax works like #[Scope]', function () {
    Book::factory()->create(['price' => 9900]);
    Book::factory()->create(['price' => 20000]);

    expect(LabBook::cheaperThan(15000)->count())->toBe(1);
});

test('ApprovedScope hides pending reviews; withoutGlobalScope shows them', function () {
    $book = Book::factory()->create();
    Review::factory()->approved()->for($book)->create();
    Review::factory()->count(2)->for($book)->create();

    expect(Review::count())->toBe(1)
        ->and($book->reviews()->count())->toBe(1)
        ->and(Book::withCount('reviews')->find($book->id)->reviews_count)->toBe(1)
        ->and(Review::withoutGlobalScope(ApprovedScope::class)->count())->toBe(3)
        ->and(Review::withoutGlobalScopes()->where('is_approved', false)->count())->toBe(2)
        ->and(Review::query()->toRawSql())->toMatch('/.reviews.\..is_approved. = 1/');
});

test('admin book page shows pending reviews (withoutGlobalScope)', function () {
    $book = Book::factory()->create();
    Review::factory()->approved()->for($book)->create();
    Review::factory()->count(2)->for($book)->create();

    $this->get(route('admin.books.show', $book))
        ->assertOk()
        ->assertSeeInOrder([__('ui.admin.fields.reviews'), '1', __('ui.admin.fields.pending_reviews'), '2']);
});

/**
 * The stage 3 catalog query (inline when() conditions), kept verbatim as the
 * reference for the scope refactor. Only the aggregate alias changed
 * (approvedReviews => reviews + ApprovedScope).
 *
 * @return Builder<Book>
 */
function legacyCatalogQuery(?Genre $genre, ?Author $author, ?BookFormat $format, bool $inStock, string $sort): Builder
{
    $genre?->load('children:id,parent_id');

    return Book::withCardData()
        ->when($genre, function (Builder $query, Genre $genre) {
            $ids = $genre->children->pluck('id')->push($genre->id);

            $query->where(fn (Builder $q) => $q
                ->whereIn('genre_id', $ids)
                ->orWhereHas('genres', fn (Builder $g) => $g->whereIn('genres.id', $ids)));
        })
        ->when($author, fn (Builder $query, Author $author) => $query
            ->whereHas('authors', fn (Builder $q) => $q->whereKey($author->id)))
        ->when($format, fn (Builder $query, BookFormat $format) => $query->where('format', $format))
        ->when($inStock, fn (Builder $query) => $query
            ->whereHas('stocks', fn (Builder $q) => $q->whereColumn('quantity', '>', 'reserved')))
        ->when($sort, fn (Builder $query, string $sort) => match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByRaw('reviews_avg_rating is null')->orderByDesc('reviews_avg_rating'),
            default => $query->orderByRaw('published_at is null')->orderByDesc('published_at'),
        })
        ->orderBy('id');
}

test('catalog: scopes return exactly what the stage 3 when() chain returned', function () {
    config(['database.seed_scale' => 'small']);
    $this->seed(DatabaseSeeder::class);

    $root = Genre::whereNull('parent_id')->has('children')->orderBy('id')->first();
    $child = $root->children()->orderBy('id')->first();
    $author = Author::has('books', '>=', 2)->orderBy('id')->first();

    $cases = [
        [null, null, null, false, 'newest'],
        [$root, null, null, false, 'newest'],
        [$child, null, null, true, 'price_asc'],
        [null, $author, null, false, 'price_desc'],
        [null, null, BookFormat::Ebook, true, 'rating'],
        [$root, null, BookFormat::Paperback, true, 'rating'],
        [$root, $author, null, false, 'bogus'],
    ];

    $nonEmpty = 0;

    foreach ($cases as [$genre, $author, $format, $inStock, $sort]) {
        $scoped = Book::withCardData()
            ->when($genre, fn ($query, Genre $genre) => $query->ofGenre($genre))
            ->when($author, fn ($query, Author $author) => $query->byAuthor($author))
            ->when($format, fn ($query, BookFormat $format) => $query->where('format', $format))
            ->when($inStock, fn ($query) => $query->inStock())
            ->sorted($sort)
            ->orderBy('id')
            ->pluck('id')->all();

        $legacy = legacyCatalogQuery($genre, $author, $format, $inStock, $sort)->pluck('id')->all();

        expect($scoped)->toBe($legacy);
        $nonEmpty += $scoped === [] ? 0 : 1;
    }

    expect($nonEmpty)->toBeGreaterThanOrEqual(5);
});
