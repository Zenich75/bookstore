<?php

namespace App\Http\Controllers\Manage;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    /**
     * Admin: every warehouse. Manager: only their own (the query itself is scoped,
     * so other warehouses are not even listed).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('manage.warehouses.index', [
            'warehouses' => Warehouse::query()
                ->when($user->role !== UserRole::Admin, fn ($query) => $query->whereKey($user->warehouse_id))
                ->with('manager:id,name,warehouse_id')
                ->withCount('stocks')
                ->withSum('stocks', 'quantity')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
