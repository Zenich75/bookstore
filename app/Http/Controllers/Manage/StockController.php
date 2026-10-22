<?php

namespace App\Http\Controllers\Manage;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\UpdateStockRequest;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Routes: /manage/warehouses/{warehouse}/stocks[/{stock}/edit].
 * warehouse.access guards the warehouse; scopeBindings() resolves {stock}
 * through $warehouse->stocks(), so a stock id from another warehouse is a 404;
 * can:update,stock checks StockPolicy for the row itself.
 */
class StockController extends Controller
{
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
            'reasons' => UpdateStockRequest::REASONS,
        ]);
    }

    public function update(UpdateStockRequest $request, Warehouse $warehouse, Stock $stock): RedirectResponse
    {
        // Transient property read by StockObserver: the movement gets this reason
        // (adjustment when none was given) and the current user (auth()->id()).
        $stock->movementReason = $request->safe()->enum('reason', StockMovementReason::class);
        // safe()->except(): validated data minus the reason, which is not a column.
        $stock->fill($request->safe()->except('reason'))->save();
        $stock->load('book:id,title'); // strict mode: no lazy loading for the flash message

        return redirect()
            ->route('manage.warehouses.stocks.index', $warehouse)
            ->with('status', __('ui.manage.flash.stock_updated', ['title' => $stock->book->title]));
    }
}
