<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Review;
use App\Models\Warehouse;
use Illuminate\View\View;

/**
 * Invokable (single-action) controller: route points at the class itself.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'counts' => [
                __('ui.home.stats.books') => Book::count(),
                __('ui.home.stats.authors') => Author::count(),
                __('ui.home.stats.warehouses') => Warehouse::count(),
                // ApprovedScope (global) already limits this to approved reviews.
                __('ui.home.stats.approved_reviews') => Review::count(),
            ],
            'latestBooks' => Book::withCardData()
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
