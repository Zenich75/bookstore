<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuthorRole;
use App\Enums\BookFormat;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Scopes\ApprovedScope;
use App\Models\Tag;
use App\ValueObjects\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Books CRUD (admin area). The routes already require role:admin; the controller
 * attributes below add the policy checks right where the actions are defined.
 *
 * Controller middleware via PHP attributes (Laravel 13): each #[Authorize] becomes
 * a "can:..." middleware for the listed actions. Compare with Admin\ReviewController,
 * which declares the same kind of middleware in HasMiddleware::middleware().
 */
#[Authorize('manage', Book::class)]
#[Authorize('restore', 'book', only: ['restore'])]
#[Authorize('forceDelete', 'book', only: ['forceDelete'])]
class BookController extends Controller
{
    private const TRASHED_FILTERS = ['with', 'only'];

    public function index(Request $request): View
    {
        $trashed = in_array($request->query('trashed'), self::TRASHED_FILTERS, true) ? $request->query('trashed') : null;

        if ($trashed !== null) {
            // A check inside the action, for a case only one branch needs.
            Gate::authorize('viewTrashed', Book::class);
        }

        $books = Book::query()
            ->when($trashed === 'with', fn ($query) => $query->withTrashed())
            ->when($trashed === 'only', fn ($query) => $query->onlyTrashed())
            ->when($request->filled('q'), fn ($query) => $query->search($request->string('q')->toString()))
            ->with('genre:id,name')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.books.index', compact('books', 'trashed'));
    }

    public function create(): View
    {
        return view('admin.books.form', ['book' => new Book, ...$this->formOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $data = $this->validated($request);

            $book = Book::create($data['attributes']); // slug: HasSlug
            $this->syncRelations($book, $data);

            return $book;
        });

        return redirect()->route('admin.books.show', $book)
            ->with('status', __('ui.admin.flash.book_created', ['title' => $book->title]));
    }

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

    public function edit(Book $book): View
    {
        $book->load(['authors:id', 'genres:id', 'tags:id']);

        return view('admin.books.form', ['book' => $book, ...$this->formOptions()]);
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        DB::transaction(function () use ($request, $book) {
            $data = $this->validated($request, $book);

            $book->update($data['attributes']); // the slug stays (see HasSlug)
            $this->syncRelations($book, $data);
        });

        return redirect()->route('admin.books.show', $book)
            ->with('status', __('ui.admin.flash.book_updated', ['title' => $book->title]));
    }

    public function destroy(Book $book): RedirectResponse
    {
        $book->delete(); // soft delete: deleted_at is set, the row stays

        return redirect()->route('admin.books.index')
            ->with('status', __('ui.admin.flash.book_deleted', ['title' => $book->title]));
    }

    /**
     * Route: ->withTrashed(), otherwise a soft-deleted {book} would be a 404.
     */
    public function restore(Book $book): RedirectResponse
    {
        $book->restore();

        return redirect()->route('admin.books.show', $book)
            ->with('status', __('ui.admin.flash.book_restored', ['title' => $book->title]));
    }

    public function forceDelete(Book $book): RedirectResponse
    {
        // Pivot rows, stocks, reviews and movements go with it (cascadeOnDelete FKs);
        // polymorphic rows (images, taggables) have no FK, so they are removed here.
        DB::transaction(function () use ($book) {
            $book->images()->delete();
            $book->tags()->detach();
            $book->forceDelete();
        });

        return redirect()->route('admin.books.index', ['trashed' => 'only'])
            ->with('status', __('ui.admin.flash.book_force_deleted', ['title' => $book->title]));
    }

    /**
     * @return array{genres: Collection<int, Genre>, authors: Collection<int, Author>, tags: Collection<int, Tag>, formats: list<BookFormat>, roles: list<AuthorRole>}
     */
    private function formOptions(): array
    {
        return [
            'genres' => Genre::orderBy('name')->get(['id', 'name', 'parent_id']),
            'authors' => Author::orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'formats' => BookFormat::cases(),
            'roles' => AuthorRole::cases(),
        ];
    }

    /**
     * Inline validation for now; moves to a Form Request in stage 8.
     *
     * @return array{attributes: array<string, mixed>, authors: array<int, array{role: string, sort_order: int}>, genres: list<int>, tags: list<int>}
     */
    private function validated(Request $request, ?Book $book = null): array
    {
        // ISBN may be typed with dashes/spaces; validate (and check uniqueness of) the digits.
        $request->merge(['isbn' => preg_replace('/\D+/', '', (string) $request->input('isbn'))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13', Rule::unique('books', 'isbn')->ignore($book)],
            'price' => ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'format' => ['required', Rule::enum(BookFormat::class)],
            'genre_id' => ['required', 'integer', 'exists:genres,id'],
            // books.published_at is a TIMESTAMP column: 1970-01-01 .. 2038-01-19 only.
            'published_at' => ['nullable', 'date', 'after:1970-01-01', 'before:2038-01-19'],
            'authors' => ['array', 'max:10'],
            'authors.*.id' => ['nullable', 'integer', 'distinct', 'exists:authors,id'],
            'authors.*.role' => ['required_with:authors.*.id', Rule::enum(AuthorRole::class)],
            'genres' => ['array'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $publishedAt = isset($data['published_at']) ? Carbon::parse($data['published_at']) : null;

        // sync() payload: [author_id => pivot columns]. Row order = display order.
        $authors = collect($data['authors'] ?? [])
            ->filter(fn (array $row) => filled($row['id'] ?? null))
            ->values()
            ->mapWithKeys(fn (array $row, int $i) => [(int) $row['id'] => ['role' => $row['role'], 'sort_order' => $i]])
            ->all();

        return [
            'attributes' => [
                'title' => $data['title'],
                'isbn' => $data['isbn'],
                'price' => Money::fromUah($data['price']),
                'format' => $data['format'],
                'genre_id' => $data['genre_id'],
                'published_at' => $publishedAt,
                'published_year' => $publishedAt?->year,
            ],
            'authors' => $authors,
            // The main genre is books.genre_id; it is not repeated as a secondary one.
            'genres' => array_values(array_diff(array_map('intval', $data['genres'] ?? []), [(int) $data['genre_id']])),
            'tags' => array_map('intval', $data['tags'] ?? []),
        ];
    }

    /**
     * sync(): attach what is new, update pivot columns of what stays, detach the rest.
     *
     * @param  array{authors: array<int, array{role: string, sort_order: int}>, genres: list<int>, tags: list<int>}  $data
     */
    private function syncRelations(Book $book, array $data): void
    {
        $book->authors()->sync($data['authors']);
        $book->genres()->sync($data['genres']);
        $book->tags()->sync($data['tags']);
    }
}
