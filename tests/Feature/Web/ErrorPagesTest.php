<?php

use App\Models\Book;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app()->setLocale('uk');
});

test('translated error pages for 419, 422 and 429', function (int $status, string $key) {
    // CSRF checks are off in tests, so the statuses are raised directly.
    Route::middleware('web')->get("/_test/abort/{$status}", fn () => abort($status));

    $this->get("/_test/abort/{$status}")
        ->assertStatus($status)
        ->assertSee(__("ui.errors.{$key}.title"))
        ->assertSee(__("ui.errors.{$key}.text"))
        ->assertSee('<html lang="uk">', false);
})->with([
    [419, 'page_expired'],
    [422, 'unprocessable'],
    [429, 'too_many_requests'],
]);

test('forms carry the CSRF token and spoofed methods', function () {
    asAdmin();
    $book = Book::factory()->create();

    $this->get(route('admin.books.edit', $book))
        ->assertSee('name="_token"', false)
        ->assertSee('name="_method" value="PUT"', false);
});
