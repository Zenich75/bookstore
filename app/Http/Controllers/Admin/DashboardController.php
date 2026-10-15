<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'pendingReviews' => Review::withoutGlobalScope(ApprovedScope::class)->where('is_approved', false)->count(),
            'trashedBooks' => Book::onlyTrashed()->latest('deleted_at')->limit(10)->get(['id', 'title', 'deleted_at']),
        ]);
    }
}
