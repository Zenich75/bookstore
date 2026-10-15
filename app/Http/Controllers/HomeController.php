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
                'Books' => Book::count(),
                'Authors' => Author::count(),
                'Warehouses' => Warehouse::count(),
                'Approved reviews' => Review::where('is_approved', true)->count(),
            ],
            'latestBooks' => BookController::cardQuery()
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
