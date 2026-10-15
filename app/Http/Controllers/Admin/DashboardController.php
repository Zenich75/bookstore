<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\View\View;

/**
 * Placeholder; access control (auth + admin role) is added in stage 7.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'trashedBooks' => Book::onlyTrashed()->latest('deleted_at')->limit(10)->get(['id', 'title', 'deleted_at']),
        ]);
    }
}
