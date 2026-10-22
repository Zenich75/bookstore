<?php

namespace App\Lab\Queues;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A collection of models: the payload holds only the ids (SerializesModels), but the
 * worker hydrates every model, all columns, before handle() runs — 1000 Book objects
 * in memory to read one column.
 */
class BooksCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MeasuresModelRestoration, Queueable;

    /**
     * @param  Collection<int, Book>  $books
     */
    public function __construct(public Collection $books) {}

    public function handle(): void
    {
        JobRunLog::record('collection', $this, [
            'models' => $this->books->count(),
            'memory_mb' => round(memory_get_peak_usage() / 1048576, 1),
        ], $this->payloadBytes, $this->restoreQueries);
    }
}
