<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Enums\QueueName;
use App\Imports\BookCsv;
use App\Models\Import;
use App\Notifications\ImportFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * First link of the import chain: is the file what we expect? If not, the job
 * fails, and a failed link stops the chain — no chunk job ever runs.
 *
 * The queue is set right here with the #[Queue] attribute — the alternative to the
 * central Queue::route() map in AppServiceProvider. An attribute (or ->onQueue()
 * at dispatch time) wins over the route. Same as: ValidateImportFile::dispatch($import)->onQueue('imports').
 */
#[Queue(QueueName::Imports)]
class ValidateImportFile implements ShouldQueue
{
    use Queueable;

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        $this->import->update(['status' => ImportStatus::Validating]);

        $path = Storage::disk('imports')->path($this->import->path);
        $csv = new BookCsv($path);

        if ($missing = $csv->missingColumns()) {
            // fail(): mark as failed right away — retrying will not fix the file.
            $this->fail(new RuntimeException(__('ui.imports.errors.missing_columns', ['columns' => implode(', ', $missing)])));

            return;
        }

        // The chunk jobs were planned from the row count taken at upload time.
        if (BookCsv::countRows($path) !== $this->import->total_rows) {
            $this->fail(new RuntimeException(__('ui.imports.errors.file_changed')));

            return;
        }

        $this->import->update(['status' => ImportStatus::Processing]);
    }

    /**
     * Called once the job has failed for good (here: on fail(), no retries).
     */
    public function failed(?Throwable $exception): void
    {
        $this->import->markFailed($exception?->getMessage() ?? __('ui.imports.errors.unknown'));
        $this->import->loadMissing('user')->user?->notify(
            new ImportFailedNotification($this->import->id, $this->import->original_name, $exception?->getMessage() ?? ''),
        );
    }
}
