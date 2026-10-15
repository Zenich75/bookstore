<?php

namespace App\Http\Controllers;

use App\Enums\BookFormat;
use App\Http\Controllers\Concerns\ShowsBook;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    use ShowsBook;

    /**
     * Sort keys accepted by the catalog; anything else falls back to "newest".
     * Labels: lang/{en,uk}/ui.php => catalog.sorts.*
     */
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'rating'];

    public function index(Request $request): View
    {
        // Input is read leniently: unknown/invalid values are simply ignored.
        // Proper validation (Form Requests) comes in stage 8.
        $genre = $request->filled('genre')
            ? Genre::firstWhere('slug', $request->string('genre')->toString())
            : null;
        $author = $request->filled('author')
            ? Author::firstWhere('slug', $request->string('author')->toString())
            : null;
        $format = $request->enum('format', BookFormat::class);
        $inStock = $request->boolean('in_stock');
        $sort = in_array($sort = $request->string('sort', 'newest')->toString(), self::SORTS, true) ? $sort : 'newest';

        // Conditional query building with when() + local scopes (Book model): each
        // filter reads like a sentence, and the same scopes serve other pages and tests.
        // Stage 3 had these conditions inline — see git history / docs/06-attributes-casts.md.
        $books = Book::withCardData()
            ->when($genre, fn ($query, Genre $genre) => $query->ofGenre($genre))
            ->when($author, fn ($query, Author $author) => $query->byAuthor($author))
            ->when($format, fn ($query, BookFormat $format) => $query->where('format', $format))
            ->when($inStock, fn ($query) => $query->inStock())
            ->sorted($sort)
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        return view('books.index', [
            'books' => $books,
            'filters' => array_filter([
                'genre' => $genre?->name,
                'author' => $author?->full_name,
                'format' => $format?->label(),
                'in_stock' => $inStock ? __('ui.catalog.chip_in_stock') : null,
            ]),
            'activeGenre' => $genre,
            'sort' => $sort,
            'formats' => BookFormat::cases(),
        ]);
    }

    public function show(Request $request, Book $book): View
    {
        return $this->bookPage($request, $book);
    }
}
