<?php

use App\Enums\BookFormat;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\Warehouse;

/**
 * Titles of the books shown on a catalog page (from the card links).
 *
 * @return list<string>
 */
function catalogTitles(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();
    preg_match_all('/<a href="[^"]+\/books\/[^"]+" class="font-medium[^"]*">([^<]+)<\/a>/', $html, $matches);

    return array_map(fn ($title) => html_entity_decode(trim($title), ENT_QUOTES), $matches[1]);
}

test('genre filter includes child genres and secondary genres', function () {
    $root = Genre::factory()->create(['slug' => 'fantasy']);
    $child = Genre::factory()->child($root)->create();
    $other = Genre::factory()->create();

    Book::factory()->for($root)->create(['title' => 'Root Book']);
    Book::factory()->for($child)->create(['title' => 'Child Book']);
    $secondary = Book::factory()->for($other)->create(['title' => 'Secondary Book']);
    $secondary->genres()->attach($root);
    Book::factory()->for($other)->create(['title' => 'Other Book']);

    expect(catalogTitles('/books?genre=fantasy'))
        ->toEqualCanonicalizing(['Root Book', 'Child Book', 'Secondary Book']);
});

test('author filter', function () {
    $author = Author::factory()->create(['slug' => 'jane-doe']);
    $book = Book::factory()->create(['title' => 'By Jane']);
    $book->authors()->attach($author, ['role' => 'author']);
    Book::factory()->create(['title' => 'Not By Jane']);

    expect(catalogTitles('/books?author=jane-doe'))->toBe(['By Jane']);
});

test('in_stock filter requires quantity above reserved in some warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $available = Book::factory()->create(['title' => 'Available']);
    $reserved = Book::factory()->create(['title' => 'Fully Reserved']);
    Book::factory()->create(['title' => 'Not Stocked']);

    $available->warehouses()->attach($warehouse, ['quantity' => 3, 'reserved' => 1]);
    $reserved->warehouses()->attach($warehouse, ['quantity' => 2, 'reserved' => 2]);

    expect(catalogTitles('/books?in_stock=1'))->toBe(['Available'])
        ->and(catalogTitles('/books'))->toHaveCount(3);
});

test('format filter, invalid values are ignored', function () {
    Book::factory()->format(BookFormat::Ebook)->create(['title' => 'Digital']);
    Book::factory()->format(BookFormat::Hardcover)->create(['title' => 'Printed']);

    expect(catalogTitles('/books?format=ebook'))->toBe(['Digital'])
        ->and(catalogTitles('/books?format=vinyl'))->toHaveCount(2)
        ->and(catalogTitles('/books?genre=nope&sort=bogus'))->toHaveCount(2);
});

test('sorting by price and rating (books without reviews last)', function () {
    $cheap = Book::factory()->create(['title' => 'Cheap', 'price' => 500]);
    $pricey = Book::factory()->create(['title' => 'Pricey', 'price' => 5000]);
    $unrated = Book::factory()->create(['title' => 'Unrated', 'price' => 1000]);

    Review::factory()->approved()->for($cheap)->create(['rating' => 5]);
    Review::factory()->approved()->for($pricey)->create(['rating' => 2]);
    Review::factory()->for($unrated)->create(['rating' => 5]); // not approved => no rating

    expect(catalogTitles('/books?sort=price_asc'))->toBe(['Cheap', 'Unrated', 'Pricey'])
        ->and(catalogTitles('/books?sort=price_desc'))->toBe(['Pricey', 'Unrated', 'Cheap'])
        ->and(catalogTitles('/books?sort=rating'))->toBe(['Cheap', 'Pricey', 'Unrated']);
});

test('pagination keeps the query string', function () {
    Book::factory()->count(30)->format(BookFormat::Ebook)->create();

    $this->get('/books?format=ebook&sort=price_asc')
        ->assertOk()
        ->assertSee('format=ebook&amp;sort=price_asc&amp;page=2', false);

    expect(catalogTitles('/books?format=ebook&sort=price_asc&page=2'))->toHaveCount(6);
});

test('active filters are shown as removable chips', function () {
    $genre = Genre::factory()->create(['slug' => 'horror', 'name' => 'Horror']);
    Book::factory()->for($genre)->format(BookFormat::Paperback)->create();

    $this->get('/books?genre=horror&format=paperback')
        ->assertSee('data-filter="genre"', false)
        ->assertSee('data-filter="format"', false)
        // The genre chip links to the catalog without the genre parameter.
        ->assertSee(route('books.index', ['format' => 'paperback']), false);
});
