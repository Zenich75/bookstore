<?php

use App\Lab\LazyLoading;
use App\Lab\QueryProfiler;
use App\Models\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Two books: Eloquent only guards lazy loading on models fetched together with
    // others (Builder::hydrate, count > 1) — a single model can't cause an N+1.
    Book::factory()->count(2)->withAuthors(2)->create();
});

test('strict mode throws on lazy loading in testing', function () {
    $book = Book::all()->first();

    expect(fn () => $book->authors)->toThrow(LazyLoadingViolationException::class);
});

test('a single fetched model may lazy load (no N+1 possible)', function () {
    expect(Book::first()->authors)->toHaveCount(2);
});

test('lazy loading is only logged in production', function () {
    $original = app()->environment();
    app()->detectEnvironment(fn () => 'production');
    Log::spy();

    try {
        $book = Book::all()->first();

        expect($book->authors)->toHaveCount(2); // loaded lazily, no exception
        Log::shouldHaveReceived('warning')->once()
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'N+1') && $context['relation'] === 'authors');
    } finally {
        app()->detectEnvironment(fn () => $original);
    }
});

test('freshly created models are exempt, like the framework default', function () {
    $book = Book::factory()->create();

    expect($book->authors)->toHaveCount(0);
});

test('LazyLoading::allowDuring allows lazy loading and restores strict mode', function () {
    $count = LazyLoading::allowDuring(fn () => Book::all()->sum(fn (Book $book) => $book->authors->count()));

    expect($count)->toBe(4)
        ->and(Model::preventsLazyLoading())->toBeTrue();

    try {
        LazyLoading::allowDuring(fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect(Model::preventsLazyLoading())->toBeTrue();
});

test('QueryProfiler counts queries and groups duplicates by normalized SQL', function () {
    $profile = LazyLoading::allowDuring(fn () => QueryProfiler::run(function () {
        return Book::all()->map(fn (Book $book) => $book->authors->count())->sum();
    }));

    expect($profile->queries)->toBe(3) // 1 for books + 1 per book (2 books)
        ->and($profile->duplicates())->toHaveCount(1)
        ->and($profile->duplicates()[0]['count'])->toBe(2)
        ->and($profile->result)->toBe(4)
        ->and($profile->wallMs)->toBeGreaterThan(0)
        ->and(DB::logging())->toBeFalse();
});

test('QueryProfiler::normalize strips bindings, literals and IN lists', function () {
    expect(QueryProfiler::normalize("select * from `books` where `id` in (1, 2, 3) and `type` = 'book' limit 1"))
        ->toBe('select * from `books` where `id` in (…) and `type` = ? limit ?');
});
