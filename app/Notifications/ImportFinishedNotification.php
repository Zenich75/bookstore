<?php

namespace App\Notifications;

use App\Models\Import;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Import report to the uploader (last link of the import chain, SendImportReport).
 */
class ImportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
        public string $fileName,
        public string $warehouse,
        public string $status,
        public int $total,
        public int $imported,
        public int $failed,
    ) {}

    public static function for(Import $import): self
    {
        return new self($import->id, $import->original_name, $import->warehouse->name, $import->status->label(),
            $import->total_rows, $import->imported, $import->failed);
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.import_finished.subject', ['file' => $this->fileName]))
            ->markdown('mail.import-finished', [
                'file' => $this->fileName,
                'warehouse' => $this->warehouse,
                'status' => $this->status,
                'total' => $this->total,
                'imported' => $this->imported,
                'failed' => $this->failed,
                'url' => route('admin.imports.show', $this->importId),
                'errorsUrl' => $this->failed > 0 ? route('admin.imports.errors', $this->importId) : null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'import_finished',
            'params' => ['file' => $this->fileName, 'imported' => $this->imported, 'failed' => $this->failed],
            'url' => route('admin.imports.show', $this->importId),
        ];
    }
}
