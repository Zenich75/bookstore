<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Warehouse;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->genre = Genre::factory()->create();
    Genre::factory()->child($this->genre)->create();
    $this->book = Book::factory()->for($this->genre)->withAuthors(2)->inWarehouses(2)->withReviews(2)->create();
    $this->author = $this->book->authors()->first();
    $this->warehouse = Warehouse::first();
});

/**
 * Named GET routes defined by the app (vendor routes such as debugbar.* excluded).
 *
 * @return list<string>
 */
function appRouteNames(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null
            && in_array('GET', $route->methods())
            && ! str_starts_with($route->getName(), 'debugbar.')
            && ! str_starts_with($route->getName(), 'storage.'))
        ->map->getName()
        ->sort()->values()->all();
}

test('every named route resolves and returns 200', function () {
    $params = [
        'books.show' => [$this->book],
        'authors.show' => [$this->author],
        'authors.books.show' => [$this->author, $this->book],
        'genres.show' => [$this->genre],
        'warehouses.show' => [$this->warehouse],
        'admin.books.show' => [$this->book],
    ];

    $tested = [];
    foreach (appRouteNames() as $name) {
        // genres.show redirects to the filtered catalog; follow it.
        $this->followingRedirects()->get(route($name, $params[$name] ?? []))
            ->assertOk();
        $tested[] = $name;
    }

    expect($tested)->toContain('home', 'books.index', 'books.show', 'authors.index', 'authors.show',
        'authors.books.show', 'genres.index', 'genres.show', 'warehouses.index', 'warehouses.show',
        'about', 'admin.dashboard', 'admin.books.show', 'lab.relations');
});

test('routes bind by slug and by id as declared', function () {
    expect(route('books.show', $this->book))->toEndWith('/books/'.$this->book->slug)
        ->and(route('authors.books.show', [$this->author, $this->book]))
        ->toEndWith("/authors/{$this->author->slug}/books/{$this->book->slug}")
        ->and(route('warehouses.show', $this->warehouse))->toEndWith('/warehouses/'.$this->warehouse->id)
        ->and(route('admin.books.show', $this->book))->toEndWith('/admin/books/'.$this->book->id);
});

test('genres.show redirects to the filtered catalog', function () {
    $this->get(route('genres.show', $this->genre))
        ->assertRedirect(route('books.index', ['genre' => $this->genre->slug]));
});

test('unknown slugs return 404', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'book' => '/books/no-such-book',
    'author' => '/authors/no-such-author',
    'genre' => '/genres/no-such-genre',
    'warehouse' => '/warehouses/999999',
]);

test('scoped binding: a book that is not by the author is a 404', function () {
    $otherAuthor = Author::factory()->create();

    $this->get(route('authors.books.show', [$this->author, $this->book]))->assertOk();
    $this->get("/authors/{$otherAuthor->slug}/books/{$this->book->slug}")->assertNotFound();
});

test('soft-deleted book: 404 publicly, 200 in admin (withTrashed)', function () {
    $this->book->delete();

    $this->get("/books/{$this->book->slug}")->assertNotFound();
    $this->get("/authors/{$this->author->slug}/books/{$this->book->slug}")->assertNotFound();
    $this->get(route('admin.books.show', $this->book))
        ->assertOk()
        ->assertSee(Str::before(__('ui.admin.soft_deleted'), ':time'));
});

test('fallback route renders the custom 404 page', function () {
    $this->get('/definitely/not/a/page')
        ->assertNotFound()
        ->assertSee(__('ui.errors.not_found.title'))
        ->assertSee(__('ui.errors.not_found.back'));
});
