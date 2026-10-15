<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * The route uses ->withTrashed(), so soft-deleted books resolve here.
     */
    public function show(Book $book): View
    {
        $book->load(['genre:id,name', 'authors:id,first_name,last_name'])->loadCount('reviews');

        return view('admin.books.show', compact('book'));
    }
}
