<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $movements = $warehouse->movements()
            // withTrashed: the ledger keeps rows of books that were soft-deleted later.
            ->with(['book' => fn ($query) => $query->withTrashed()->select(['id', 'title', 'slug']), 'user:id,name'])
            ->latest('id')
            ->paginate(30);

        return view('manage.movements.index', compact('warehouse', 'movements'));
    }
}
