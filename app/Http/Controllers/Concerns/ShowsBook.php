<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Book page shared by books.show and the scoped authors.books.show route.
 */
trait ShowsBook
{
    protected function bookPage(Request $request, Book $book, ?Author $viaAuthor = null): View
    {
        // Load everything the view touches (strict mode: no lazy loading).
        $book->load([
            'genre.parent',
            'genres:id,name,slug',
            'authors:id,first_name,last_name,slug',
            'tags:id,name',
            'cover',
            'warehouses' => fn ($query) => $query->orderBy('name'),
            'latestReview.user:id,name',
            'bestReview.user:id,name',
        ])->loadCount('reviews')->loadAvg('reviews', 'rating');

        // Approved only: Review's global ApprovedScope applies to relation queries too.
        $reviews = $book->reviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('books.show', compact('book', 'reviews', 'viaAuthor'));
    }
}
