<?php

namespace App\Http\Controllers\Manage;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Routes: /manage/warehouses/{warehouse}/stocks[/{stock}/edit].
 * warehouse.access guards the warehouse; scopeBindings() resolves {stock}
 * through $warehouse->stocks(), so a stock id from another warehouse is a 404;
 * can:update,stock checks StockPolicy for the row itself.
 */
class StockController extends Controller
{
    /**
     * Reasons a manager may pick; "import" is reserved for bulk imports.
     */
    private const REASONS = [StockMovementReason::Receipt, StockMovementReason::Sale, StockMovementReason::Adjustment];

    public function index(Warehouse $warehouse): View
    {
        $warehouse->load('manager:id,name,warehouse_id');

        $stocks = $warehouse->stocks()
            ->whereHas('book') // skip soft-deleted books
            ->with('book:id,title,slug')
            ->orderBy('shelf_code')
            ->orderBy('id')
            ->paginate(25);

        return view('manage.stocks.index', compact('warehouse', 'stocks'));
    }

    public function edit(Warehouse $warehouse, Stock $stock): View
    {
        $stock->load('book:id,title,slug');

        return view('manage.stocks.edit', [
            'warehouse' => $warehouse,
            'stock' => $stock,
            'reasons' => self::REASONS,
        ]);
    }

    public function update(Request $request, Warehouse $warehouse, Stock $stock): RedirectResponse
    {
        // Inline validation for now; moves to a Form Request in stage 8.
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reserved' => ['required', 'integer', 'min:0', 'lte:quantity'],
            'shelf_code' => ['nullable', 'string', 'max:20'],
            'reason' => ['required', Rule::enum(StockMovementReason::class)->only(self::REASONS)],
        ]);

        // Transient property read by StockObserver: the movement gets this reason
        // and the current user (auth()->id()).
        $stock->movementReason = StockMovementReason::from($data['reason']);
        $stock->fill(collect($data)->except('reason')->all())->save();
        $stock->load('book:id,title'); // strict mode: no lazy loading for the flash message

        return redirect()
            ->route('manage.warehouses.stocks.index', $warehouse)
            ->with('status', __('ui.manage.flash.stock_updated', ['title' => $stock->book->title]));
    }
}
