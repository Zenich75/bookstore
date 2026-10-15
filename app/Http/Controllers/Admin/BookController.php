<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Scopes\ApprovedScope;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * The route uses ->withTrashed(), so soft-deleted books resolve here.
     */
    public function show(Book $book): View
    {
        $book->load(['genre:id,name', 'authors:id,first_name,last_name'])->loadCount([
            'reviews',
            // Moderation needs the pending ones too: switch the global scope off.
            'reviews as pending_reviews_count' => fn ($query) => $query
                ->withoutGlobalScope(ApprovedScope::class)
                ->where('is_approved', false),
        ]);

        return view('admin.books.show', compact('book'));
    }
}
