<?php

use App\Jobs\RecalculateBookRating;
use App\Jobs\RecalculateBookRatings;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

test('the average is computed in SQL from approved reviews only, rounded to 2 places', function () {
    $book = Book::factory()->create();
    $empty = Book::factory()->create(['rating_avg' => 4.0]);
    foreach ([5, 4, 4] as $rating) {
        Review::factory()->approved()->for($book)->create(['rating' => $rating]);
    }
    Review::factory()->for($book)->create(['rating' => 1]); // pending

    RecalculateBookRating::dispatchSync($book->id);
    RecalculateBookRating::dispatchSync($empty->id);

    expect((float) $book->fresh()->rating_avg)->toBe(4.33)
        ->and($empty->fresh()->rating_avg)->toBeNull(); // no approved reviews any more
});

test('ShouldBeUniqueUntilProcessing: one queued job per book until a worker starts it', function () {
    config(['queue.default' => 'database']); // really queue (tests default to sync); cache: array
    [$a, $b] = Book::factory()->count(2)->create();

    RecalculateBookRating::dispatch($a->id);
    RecalculateBookRating::dispatch($a->id); // same uniqueId => dropped
    RecalculateBookRating::dispatch($b->id);

    expect(DB::table('jobs')->where('queue', 'ratings')->count())->toBe(2);

    // A worker picks up the first job: the lock is released when processing *starts*,
    // so a change arriving now queues a fresh recalculation.
    $this->artisan('queue:work', ['--once' => true, '--queue' => 'ratings'])->assertSuccessful();
    RecalculateBookRating::dispatch($a->id);

    expect(DB::table('jobs')->where('queue', 'ratings')->count())->toBe(2);
});

test('books:recalculate-ratings --sync fills ratings in-process', function () {
    $book = Book::factory()->create();
    Review::factory()->approved()->for($book)->create(['rating' => 3]);

    $this->artisan('books:recalculate-ratings', ['--sync' => true])->assertSuccessful();

    expect((float) $book->fresh()->rating_avg)->toBe(3.0);
});

test('books:recalculate-ratings queues a batch of chunk jobs over lazyById', function () {
    Bus::fake();
    Book::factory()->count(5)->create();

    $this->artisan('books:recalculate-ratings', ['--chunk' => 2])->assertSuccessful();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 3
        && $batch->jobs->every(fn ($job) => $job instanceof RecalculateBookRatings)
        && $batch->jobs->pluck('bookIds')->flatten()->count() === 5);
});
