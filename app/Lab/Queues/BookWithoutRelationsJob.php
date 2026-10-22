<?php

namespace App\Lab\Queues;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;

/**
 * #[WithoutRelations] on the property: the identifier is stored without relations,
 * so the worker runs one query for the book and handle() loads what it needs.
 * Same effect at dispatch time: new BookWithRelationsJob($book->withoutRelations()).
 */
class BookWithoutRelationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MeasuresModelRestoration, Queueable;

    public function __construct(
        #[WithoutRelations]
        public Book $book,
    ) {}

    public function handle(): void
    {
        JobRunLog::record('without_relations', $this, [
            'relations' => array_keys($this->book->getRelations()),
        ], $this->payloadBytes, $this->restoreQueries);
    }
}
