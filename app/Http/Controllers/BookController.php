<?php

namespace App\Http\Controllers;

use App\Enums\BookFormat;
use App\Http\Controllers\Concerns\ShowsBook;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Everything <x-book-card> reads. Strict mode is on, so the card would throw
     * on any relation/aggregate that is not loaded here.
     * (Moves to a local scope in stage 6.)
     *
     * @return Builder<Book>
     */
    public static function cardQuery(): Builder
    {
        return Book::query()
            ->with(['genre:id,name,slug', 'authors:id,first_name,last_name,slug', 'cover'])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->withSum('stocks', 'quantity');
    }

    public function index(Request $request): View
    {
        // Input is read leniently: unknown/invalid values are simply ignored.
        // Proper validation (Form Requests) comes in stage 8.
        $genre = $request->filled('genre')
            ? Genre::with('children:id,parent_id')->firstWhere('slug', $request->string('genre')->toString())
            : null;
        $author = $request->filled('author')
            ? Author::firstWhere('slug', $request->string('author')->toString())
            : null;
        $format = $request->enum('format', BookFormat::class);
        $inStock = $request->boolean('in_stock');
        $sort = in_array($sort = $request->string('sort', 'newest')->toString(), self::SORTS, true) ? $sort : 'newest';

        // Conditional query building with when(). These conditions move to
        // reusable local scopes in stage 6.
        $books = self::cardQuery()
            ->when($genre, function (Builder $query, Genre $genre) {
                // The genre itself and its sub-genres, as main or secondary genre.
                $ids = $genre->children->pluck('id')->push($genre->id);

                $query->where(fn (Builder $q) => $q
                    ->whereIn('genre_id', $ids)
                    ->orWhereHas('genres', fn (Builder $g) => $g->whereIn('genres.id', $ids)));
            })
            ->when($author, fn (Builder $query, Author $author) => $query
                ->whereHas('authors', fn (Builder $q) => $q->whereKey($author->id)))
            ->when($format, fn (Builder $query, BookFormat $format) => $query->where('format', $format))
            ->when($inStock, fn (Builder $query) => $query
                ->whereHas('stocks', fn (Builder $q) => $q->whereColumn('quantity', '>', 'reserved')))
            ->when($sort, fn (Builder $query, string $sort) => match ($sort) {
                'price_asc' => $query->orderBy('price'),
                'price_desc' => $query->orderByDesc('price'),
                // Average of approved reviews; books without reviews go last.
                // (books.rating_avg is filled by a queued job in a later stage.)
                'rating' => $query->orderByRaw('approved_reviews_avg_rating is null')
                    ->orderByDesc('approved_reviews_avg_rating'),
                default => $query->orderByRaw('published_at is null')->orderByDesc('published_at'),
            })
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        return view('books.index', [
            'books' => $books,
            'filters' => array_filter([
                'genre' => $genre?->name,
                'author' => $author ? $author->first_name.' '.$author->last_name : null,
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
