<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent from a job's failed() hook: the import (or one of its chunks) gave up
 * after all retries.
 */
class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
        public string $fileName,
        public string $reason,
    ) {}

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
            ->error()
            ->subject(__('mail.import_failed.subject', ['file' => $this->fileName]))
            ->markdown('mail.import-failed', [
                'file' => $this->fileName,
                'reason' => $this->reason,
                'url' => route('admin.imports.show', $this->importId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'import_failed',
            'params' => ['file' => $this->fileName],
            'url' => route('admin.imports.show', $this->importId),
        ];
    }
}
