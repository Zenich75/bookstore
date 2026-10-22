<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuthorRole;
use App\Enums\BookFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBookRequest;
use App\Http\Requests\Admin\UpdateBookRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Scopes\ApprovedScope;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    /**
     * StoreBookRequest has already authorized and validated the input by the time
     * this runs; a failure never reaches the action.
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = Book::create($request->bookAttributes()); // blank slug: HasSlug
            $this->syncRelations($book, $request);

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

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        DB::transaction(function () use ($request, $book) {
            $book->update($request->bookAttributes()); // a blank slug field keeps the current slug
            $this->syncRelations($book, $request);
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
     * sync(): attach what is new, update pivot columns of what stays, detach the rest.
     */
    private function syncRelations(Book $book, StoreBookRequest $request): void
    {
        $book->authors()->sync($request->authorCredits());
        $book->genres()->sync($request->secondaryGenreIds());
        $book->tags()->sync($request->tagIds());
    }
}
