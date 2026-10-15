<?php

use App\Enums\AuthorRole;
use App\Enums\ImageType;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Profile;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use App\Models\Warehouse;

/**
 * $count catalog books, each with 2 authors, 2 approved reviews, a cover,
 * stock in a warehouse, a tag and a secondary genre.
 */
function seedCatalogBooks(int $count): void
{
    $warehouse = Warehouse::factory()->create();
    $tag = Tag::factory()->create();
    $secondary = Genre::factory()->create();

    Book::factory()->count($count)->published()->withAuthors(2)->create()->each(function (Book $book) use ($warehouse, $tag, $secondary) {
        Review::factory()->count(2)->approved()->for($book)->create();
        $book->images()->create(['path' => "covers/{$book->slug}.jpg", 'type' => ImageType::Cover]);
        $book->warehouses()->attach($warehouse, ['quantity' => 10, 'reserved' => 1]);
        $book->tags()->attach($tag);
        $book->genres()->attach($secondary);
    });
}

test('home page', function () {
    expectConstantQueries(
        fn (int $n) => seedCatalogBooks($n),
        fn () => $this->get(route('home')),
    );
});

test('catalog (books.index)', function () {
    expectConstantQueries(
        fn (int $n) => seedCatalogBooks($n),
        fn () => $this->get(route('books.index')),
    );
});

test('catalog with filters and rating sort', function () {
    expectConstantQueries(
        function (int $n) {
            seedCatalogBooks($n);

            return Genre::whereNull('parent_id')->value('slug');
        },
        fn (string $genre) => $this->get(route('books.index', ['genre' => $genre, 'in_stock' => 1, 'sort' => 'rating'])),
    );
});

test('book page (books.show) with many authors, reviews, tags, genres and warehouses', function () {
    expectConstantQueries(
        function (int $n) {
            $book = Book::factory()->withAuthors($n)->inWarehouses($n)->create();
            $book->tags()->attach(Tag::factory()->count($n)->create());
            $book->genres()->attach(Genre::factory()->count($n)->child()->create());
            $book->images()->create(['path' => 'covers/x.jpg', 'type' => ImageType::Cover]);

            User::factory()->count($n)->has(Profile::factory())->create()
                ->each(fn (User $user) => Review::factory()->approved()->for($book)->for($user)->create());

            return $book;
        },
        fn (Book $book) => $this->get(route('books.show', $book)),
    );
});

test('author page (authors.show) with books in every role', function () {
    expectConstantQueries(
        function (int $n) {
            $author = Author::factory()->create();
            $author->images()->create(['path' => 'authors/x.jpg', 'type' => ImageType::Photo]);

            Book::factory()->count($n)->create()->each(fn (Book $book, int $i) => $author->books()->attach($book, [
                'role' => AuthorRole::cases()[$i % 3]->value,
                'sort_order' => 0,
            ]));

            return $author;
        },
        fn (Author $author) => $this->get(route('authors.show', $author)),
    );
});

test('warehouse page (warehouses.show)', function () {
    expectConstantQueries(
        function (int $n) {
            $warehouse = Warehouse::factory()->create();
            User::factory()->manager()->create(['warehouse_id' => $warehouse->id]);

            Book::factory()->count($n)->create()
                ->each(fn (Book $book) => $warehouse->books()->attach($book, ['quantity' => 3, 'shelf_code' => 'A-01-1']));

            return $warehouse;
        },
        fn (Warehouse $warehouse) => $this->get(route('warehouses.show', $warehouse)),
    );
});

test('genre tree view composer (catalog sidebar) and genres.index', function () {
    $seed = fn (int $n) => Genre::factory()->count($n)->create()
        ->each(fn (Genre $root) => Genre::factory()->count(2)->child($root)->create());

    expectConstantQueries($seed, fn () => $this->get(route('books.index')));
    expectConstantQueries($seed, fn () => $this->get(route('genres.index')));
});

test('index pages (authors, warehouses)', function () {
    expectConstantQueries(
        fn (int $n) => Author::factory()->count($n)->withBooks(2)->create(),
        fn () => $this->get(route('authors.index')),
    );

    expectConstantQueries(
        fn (int $n) => Warehouse::factory()->count($n)->create()
            ->each(fn (Warehouse $w) => User::factory()->manager()->create(['warehouse_id' => $w->id])),
        fn () => $this->get(route('warehouses.index')),
    );
});
