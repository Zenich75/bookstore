<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(): object
    {
        $books = Book::all();
        return view('books.index', compact('books'));
    }

    public function show($id): object
    {
        $book = Book::find($id)->with('author', 'genre')->first();
        dd($book);
        return view('books.show', compact('book'));
    }

    public function create(): object
    {
        $authors = Author::all();
        $genres = Genre::all();
        return view('books.create', compact('authors', 'genres'));
    }

    public function store(Request $request): object
    {
        $book = new Book();
        $book->title = $request->title;
        $book->author_id = $request->author_id;
        $book->save();
        $book->genres()->attach($request->genres);
        return redirect()->route('books.index');
    }

    public function edit($id)
    {
        $book = Book::find($id);
        $authors = Author::all();
        $genres = Genre::all();
        return view('books.edit', compact('book', 'authors', 'genres'));
    }

    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        $book->title = $request->title;
        $book->author_id = $request->author_id;
        $book->save();
        $book->genres()->sync($request->genres);
        return redirect()->route('books.index');
    }

    public function destroy($id)
    {
        $book = Book::find($id);
        $book->delete();
        return redirect()->route('books.index');
    }

    public function list($sort = null): object
    {
        echo $sort;

        $booksQuery = Book::query()
            ->with('author', 'genre');

        if ($sort === 'title') {
            $booksQuery->orderBy('title');
        } elseif ($sort === 'author') {
            //$booksQuery->orderBy('author.first_name');
            $booksQuery->leftJoin('authors', 'author_id', '=', 'id')
                ->orderBy('author.first_name');
        } elseif ($sort === 'genre') {
            $booksQuery->orderBy('genre.genre');
        }

        $books = $booksQuery->paginate(9);

        return view('bookstore.layouts.app', [
            'books' => $books
        ]);
    }
}
