<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Lab\LabNPlusOneController;
use App\Http\Controllers\Lab\LabRelationsController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// Invokable controller: a single __invoke() action, referenced by class name.
Route::get('/', HomeController::class)->name('home');

// Resource controller limited to two actions; scoped() binds {book} by slug
// instead of id => /books/{book:slug}.
Route::resource('books', BookController::class)
    ->only(['index', 'show'])
    ->scoped(['book' => 'slug']);

// Controller group: actions are referenced by method name only.
Route::controller(AuthorController::class)->prefix('authors')->name('authors.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{author:slug}', 'show')->name('show');

    // Scoped binding: {book:slug} is looked up via $author->books(),
    // so /authors/a/books/b is a 404 when author "a" didn't write book "b".
    Route::get('/{author:slug}/books/{book:slug}', 'book')->name('books.show')->scopeBindings();
});

// Plain [Controller::class, 'method'] routes; genres.show only redirects.
Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');
Route::get('/genres/{genre:slug}', [GenreController::class, 'show'])->name('genres.show');

// Default implicit binding by primary key: /warehouses/{warehouse} => id.
Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');

// View route (no controller); the page uses the legacy @extends layout for comparison.
Route::view('/about', 'about')->name('about');

// Admin area. Middleware (auth + admin role) is added in stage 7.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // withTrashed(): soft-deleted books still resolve (public routes return 404 for them).
    Route::get('/books/{book}', [Admin\BookController::class, 'show'])->name('books.show')->withTrashed();
});

// Lab pages (stage demos).
Route::prefix('lab')->name('lab.')->group(function () {
    Route::get('/relations', LabRelationsController::class)->name('relations');
    Route::get('/n-plus-one', LabNPlusOneController::class)->name('n-plus-one');
});

// Any unmatched URL => our custom 404 page (instead of the framework's default).
Route::fallback(fn () => response()->view('errors.404', [], 404));
