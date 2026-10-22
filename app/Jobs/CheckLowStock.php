<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Low-stock digest: one notification per warehouse to its manager, listing the
 * books whose available quantity (quantity - reserved) is below the threshold.
 *
 * Without a warehouse id (the daily scheduled run) the job fans out: one job per
 * warehouse, so warehouses are checked in parallel and one failure does not
 * stop the others.
 */
class CheckLowStock implements ShouldQueue
{
    use Queueable;

    /**
     * Rows shown in one digest; the mail says how many more there are.
     */
    public const DIGEST_LIMIT = 50;

    public function __construct(public ?int $warehouseId = null) {}

    /**
     * Job middleware runs around handle() on the worker. WithoutOverlapping takes a
     * cache lock per warehouse: if the nightly check and an import's check for the
     * same warehouse meet, the second one is released back to the queue (retry in
     * 30 s) instead of sending a duplicate digest. expireAfter() frees the lock
     * should a worker die mid-job.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('warehouse:'.($this->warehouseId ?? 'all')))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(): void
    {
        if ($this->warehouseId === null) {
            Warehouse::query()->pluck('id')->each(fn (int $id) => self::dispatch($id));

            return;
        }

        $warehouse = Warehouse::with('manager')->find($this->warehouseId);

        if ($warehouse === null) {
            return;
        }

        $threshold = config('warehouse.low_stock_threshold');
        $low = $warehouse->stocks()
            ->whereHas('book') // skip soft-deleted books
            ->whereRaw('quantity - reserved < ?', [$threshold])
            ->with('book:id,title,isbn');

        $total = (clone $low)->count();

        if ($total === 0) {
            return;
        }

        if ($warehouse->manager === null) {
            Log::warning("Low stock in warehouse #{$warehouse->id}, but it has no manager to notify.", ['items' => $total]);

            return;
        }

        // Plain arrays, not models: the queued notification carries a snapshot and
        // needs no queries (and no SerializesModels round trip) when it is sent.
        $items = $low->orderByRaw('quantity - reserved')->orderBy('id')->limit(self::DIGEST_LIMIT)->get()
            ->map(fn (Stock $stock) => [
                'title' => $stock->book->title,
                'isbn' => $stock->book->isbn,
                'shelf' => $stock->shelf_code,
                'available' => max(0, $stock->quantity - $stock->reserved),
            ])->all();

        $warehouse->manager->notify(new LowStockNotification($warehouse->id, $warehouse->name, $items, $total, $threshold));
    }
}
