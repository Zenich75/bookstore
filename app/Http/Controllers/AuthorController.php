<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShowsBook;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthorController extends Controller
{
    use ShowsBook;

    public function index(): View
    {
        return view('authors.index', [
            'authors' => Author::withCount('books')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(30),
        ]);
    }

    public function show(Author $author): View
    {
        // Only the columns the list shows (pivot columns are added by the relation).
        $author->load(['photo', 'books' => fn ($query) => $query
            ->select(['books.id', 'books.title', 'books.slug', 'books.published_year'])
            ->orderBy('title')]);

        return view('authors.show', [
            'author' => $author,
            // Grouped by the pivot role: author / co_author / translator.
            'booksByRole' => $author->books->groupBy(fn (Book $book) => $book->credit->role),
        ]);
    }

    /**
     * Scoped binding: {book:slug} is resolved through $author->books(), so a book
     * that this author didn't work on is a 404.
     */
    public function book(Request $request, Author $author, Book $book): View
    {
        return $this->bookPage($request, $book, $author);
    }
}
