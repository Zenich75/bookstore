<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        return view('warehouses.index', [
            'warehouses' => Warehouse::with('manager:id,name,warehouse_id')
                ->withCount('stocks')
                ->withSum('stocks', 'quantity')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Warehouse $warehouse): View
    {
        $warehouse->load('manager:id,name,warehouse_id');

        $stocks = $warehouse->stocks()
            ->whereHas('book') // skip soft-deleted books
            ->with('book:id,title,slug')
            ->orderBy('shelf_code')
            ->orderBy('id')
            ->paginate(25);

        return view('warehouses.show', compact('warehouse', 'stocks'));
    }
}
