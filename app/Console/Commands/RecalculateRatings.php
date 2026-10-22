<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateBookRating;
use App\Jobs\RecalculateBookRatings;
use App\Models\Book;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\LazyCollection;

#[Signature('books:recalculate-ratings {--sync : Recalculate in this process instead of queueing a batch} {--chunk=1000}')]
#[Description('Recalculate books.rating_avg from approved reviews')]
class RecalculateRatings extends Command
{
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));

        // lazyById(): keyset pagination (where id > last) — constant memory, stable
        // while rows change. withTrashed(): trashed books keep a correct rating too.
        /** @var LazyCollection<int, list<int>> $chunks */
        $chunks = Book::withTrashed()->select('id')->lazyById($chunk)
            ->map(fn (Book $book) => $book->id)
            ->chunk($chunk)
            ->map(fn (LazyCollection $ids) => $ids->values()->all());

        if ($this->option('sync')) {
            $updated = $chunks->sum(fn (array $ids) => RecalculateBookRating::forBooks($ids));
            $this->info("Ratings recalculated ({$updated} books changed).");

            return self::SUCCESS;
        }

        $batch = Bus::batch($chunks->map(fn (array $ids) => new RecalculateBookRatings($ids))->all())
            ->name('Recalculate book ratings')
            ->dispatch();

        $this->info("Batch {$batch->id} queued: {$batch->totalJobs} jobs.");

        return self::SUCCESS;
    }
}
