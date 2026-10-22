<?php

namespace App\Lab\Queues;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * SerializesModels stores the book as ModelIdentifier(class, id, *relations*).
 * The worker re-queries the book and re-runs every eager load that was loaded at
 * dispatch time — reviews.user included — whether handle() needs them or not.
 */
class BookWithRelationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MeasuresModelRestoration, Queueable;

    public function __construct(public Book $book, public string $variant = 'with_relations') {}

    public function handle(): void
    {
        JobRunLog::record($this->variant, $this, [
            'relations' => array_keys($this->book->getRelations()),
            'memory_mb' => round(memory_get_usage() / 1048576, 1),
        ], $this->payloadBytes, $this->restoreQueries);
    }
}
