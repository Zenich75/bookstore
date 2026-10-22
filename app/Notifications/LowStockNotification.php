<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily (and post-import) digest for a warehouse manager.
 *
 * ShouldQueue: each channel becomes its own SendQueuedNotifications job on the
 * "notifications" queue (Queue::route() in AppServiceProvider); a slow SMTP server
 * delays only the mail, not the database entry.
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title: string, isbn: string, shelf: string|null, available: int}>  $items  snapshot, max CheckLowStock::DIGEST_LIMIT rows
     */
    public function __construct(
        public int $warehouseId,
        public string $warehouseName,
        public array $items,
        public int $total,
        public int $threshold,
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
            ->subject(__('mail.low_stock.subject', ['warehouse' => $this->warehouseName, 'count' => $this->total]))
            ->markdown('mail.low-stock', [
                'warehouse' => $this->warehouseName,
                'items' => $this->items,
                'total' => $this->total,
                'threshold' => $this->threshold,
                'url' => route('manage.warehouses.stocks.index', $this->warehouseId),
            ]);
    }

    /**
     * Stored as JSON in notifications.data; the text is translated when displayed.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'params' => ['warehouse' => $this->warehouseName, 'count' => $this->total],
            'url' => route('manage.warehouses.stocks.index', $this->warehouseId),
        ];
    }
}
