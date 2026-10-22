<?php

namespace App\Observers;

use App\Enums\StockMovementReason;
use App\Models\Stock;
use App\Models\StockMovement;

/**
 * Writes the stock_movements ledger whenever a Stock row is created or its
 * quantity changes through Eloquent (save, attach, sync, updateExistingPivot,
 * increment). Query-level updates (Stock::where()->update(), DB::table) skip
 * model events and therefore leave no movement — see docs/06-attributes-casts.md.
 */
class StockObserver
{
    public function created(Stock $stock): void
    {
        $this->record($stock, (int) ($stock->quantity ?? 0), $stock->movementReason ?? StockMovementReason::Receipt);
    }

    public function updated(Stock $stock): void
    {
        if (! $stock->wasChanged('quantity')) {
            return;
        }

        $delta = (int) $stock->quantity - (int) $stock->getOriginal('quantity');

        $this->record($stock, $delta, $stock->movementReason ?? StockMovementReason::Adjustment);
    }

    private function record(Stock $stock, int $delta, StockMovementReason $reason): void
    {
        // Reason and source are per operation, not per model: reset them for the next save.
        $source = $stock->movementSource;
        $stock->movementReason = null;
        $stock->movementSource = [];

        if ($delta === 0) {
            return;
        }

        StockMovement::create([
            'warehouse_id' => $stock->warehouse_id,
            'book_id' => $stock->book_id,
            'user_id' => auth()->id(),
            'delta' => $delta,
            'reason' => $reason,
            ...$source,
        ]);
    }
}
