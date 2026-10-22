<?php

namespace App\Jobs;

use App\Models\Import;
use App\Notifications\ImportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Last link of the import chain: mail + database notification to the uploader.
 * Runs after the batch's finally callback has settled the status.
 */
class SendImportReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        $import = $this->import->fresh(['user', 'warehouse']);

        $import?->user?->notify(ImportFinishedNotification::for($import));
    }
}
