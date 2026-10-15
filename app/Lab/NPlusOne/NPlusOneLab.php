<?php

namespace App\Lab\NPlusOne;

use App\Lab\LazyLoading;
use App\Lab\Models\LabBook;
use App\Lab\Profile;
use App\Lab\QueryProfiler;
use App\Models\Author;
use App\Models\Book;
use App\Models\Image;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ViewException;
use Throwable;

/**
 * Registry of N+1 variants for /lab/n-plus-one.
 *
 * Every variant gets the same 50 book ids and renders the same partial
 * (lab/n-plus-one/_card), so the only difference is how data is loaded.
 */
final class NPlusOneLab
{
    public const SAMPLE_SIZE = 50;

    /**
     * @var array<string, Variant>
     */
    private array $variants = [];

    /**
     * @var list<int>|null
     */
    private ?array $bookIds = null;

    public function __construct()
    {
        $this->define();
    }

    /**
     * @return array<string, Variant>
     */
    public function variants(): array
    {
        return $this->variants;
    }

    public function variant(string $key): ?Variant
    {
        return $this->variants[$key] ?? null;
    }

    /**
     * The fixed sample: first 50 books by id (resolved once, outside profiling).
     *
     * @return list<int>
     */
    public function bookIds(): array
    {
        return $this->bookIds ??= Book::orderBy('id')->limit(self::SAMPLE_SIZE)->pluck('id')->all();
    }

    public function profile(Variant $variant): Profile
    {
        $ids = $this->bookIds();
        $callback = fn () => ($variant->run)($ids);

        if ($variant->allowMissingAttributes) {
            $inner = $callback;
            $callback = fn () => LazyLoading::allowMissingAttributesDuring($inner);
        }

        if ($variant->lazy) {
            $inner = $callback;
            $callback = fn () => LazyLoading::allowDuring($inner);
        }

        return QueryProfiler::run($callback);
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  string|null  $feed  relation holding the reviews to list (feed variants)
     */
    private function render(Collection $books, ?string $feed = null, bool $showReviewBook = false): string
    {
        return view('lab.n-plus-one._books', compact('books', 'feed', 'showReviewBook'))->render();
    }

    /**
     * @param  Collection<int, Image>  $images
     */
    private function renderImages(Collection $images): string
    {
        return view('lab.n-plus-one._images', compact('images'))->render();
    }

    private function exception(Throwable $e): string
    {
        // Unwrap ViewException(s) down to the original exception.
        while ($e instanceof ViewException && $e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }

        return '<p class="text-red-700"><strong>'.e(class_basename($e)).':</strong> '.e($e->getMessage()).'</p>';
    }

    /**
     * Book images and photos of the sample books' authors.
     *
     * @param  list<int>  $ids
     * @return Builder<Image>
     */
    private function imagesQuery(array $ids): Builder
    {
        return Image::query()
            ->where(fn ($q) => $q->where('imageable_type', 'book')->whereIn('imageable_id', $ids))
            ->orWhere(fn ($q) => $q->where('imageable_type', 'author')->whereIn(
                'imageable_id',
                DB::table('author_book')->select('author_id')->whereIn('book_id', $ids),
            ))
            ->orderBy('id');
    }

    private function add(string $key, string $label, string $title, string $description, Closure $run, bool $lazy = false, bool $allowMissingAttributes = false, bool $ranked = true): void
    {
        $this->variants[$key] = new Variant($key, $label, $title, $description, $run, $lazy, $allowMissingAttributes, $ranked);
    }

    private function define(): void
    {
        $this->add('bad', 'a', 'All lazy',
            'No eager loading. Blade touches authors, genre, cover and the review count of every book: 1 + 4 × 50 queries. Runs inside LazyLoading::allowDuring().',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)->get();

                return $this->render($books);
            }, lazy: true);

        $this->add('strict', 'b', 'All lazy, strict mode on',
            'The same code without allowDuring(): strict mode turns the first lazy load into an exception instead of 200 silent queries.',
            function (array $ids) {
                try {
                    $books = Book::whereIn('id', $ids)->get();

                    return $this->render($books);
                } catch (ViewException $e) {
                    // Blade wraps exceptions thrown while rendering (once per nested view).
                    return $this->exception($e);
                }
            }, ranked: false);

        $this->add('with-basic', 'c', 'with([authors, genre])',
            'Eager-loads two relations: 3 queries instead of 101, but cover and review count are still lazy (2 × 50).',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)->with(['authors', 'genre'])->get();

                return $this->render($books);
            }, lazy: true);

        $this->add('with-full', 'd', 'with() + cover + withCount',
            'Everything the card needs is loaded up front; the count is a subquery. Constant query count, runs with strict mode on.',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books);
            });

        $this->add('count-in-php', 'e', '->approvedReviews->count() instead of withCount',
            'No N+1, but every review is hydrated just to be counted. Compare memory with (d), which gets the same number from a COUNT(*) subquery.',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover', 'approvedReviews'])
                    ->get();

                // _card falls back to $book->approvedReviews->count() when there is no *_count attribute.
                return $this->render($books);
            });

        $this->add('feed-lazy', 'f', 'Feed: reviews.user.profile lazily',
            'The feed lists reviews with reviewer name and avatar: per book reviews, per review user, per user profile — N+1 nested three levels deep.',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)->get();

                return $this->render($books, feed: 'approvedReviews');
            }, lazy: true);

        $this->add('feed-nested', 'g', "Feed: with('approvedReviews.user.profile')",
            'Dot notation eager-loads the whole chain: one query per level, regardless of how many books or reviews.',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover', 'approvedReviews.user.profile'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books, feed: 'approvedReviews');
            });

        $this->add('feed-limited', 'h', 'Feed: latest 3 approved reviews per book',
            'A constrained eager load with ->limit(3). Since Laravel 11 the limit is applied per parent: the SQL wraps the query in ROW_NUMBER() OVER (PARTITION BY book_id ORDER BY id DESC) and keeps rows <= 3. Fewer reviews, users and profiles are loaded than in (g).',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->with([
                        'authors', 'genre', 'cover',
                        'approvedReviews' => fn ($query) => $query->latest('id')->limit(3),
                        'approvedReviews.user.profile',
                    ])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books, feed: 'approvedReviews');
            });

        $this->add('load-missing', 'i', 'load() / loadMissing() on a fetched collection',
            'When the collection already exists (e.g. passed from elsewhere), load() eager-loads for all models at once. loadMissing() skips relations that are already loaded — the second call runs no queries.',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)->get();

                $books->load(['authors', 'genre', 'cover'])->loadCount('approvedReviews');
                $books->loadMissing(['authors', 'genre']); // already loaded: no queries

                return $this->render($books);
            });

        $this->add('images-lazy', 'j1', 'Images feed: morphTo, nested relations lazy',
            "with('imageable') loads owners with one query per type, but the book's genre and the author's books are then lazy-loaded per image.",
            function (array $ids) {
                $images = $this->imagesQuery($ids)->with('imageable')->get();

                return $this->renderImages($images);
            }, lazy: true);

        $this->add('images-morph-with', 'j2', 'Images feed: morphWith()',
            'morphWith() declares nested eager loads per morph type: books get genre, authors get books.',
            function (array $ids) {
                $images = $this->imagesQuery($ids)
                    ->with(['imageable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                        Book::class => ['genre'],
                        Author::class => ['books'],
                    ])])
                    ->get();

                return $this->renderImages($images);
            });

        $this->add('over-eager', 'k1', 'Over-eager: load everything',
            'No N+1, but loads relations the page never shows (images, tags, genres, warehouses, stocks, every review with user and profile) and every column. More queries, rows and memory than (d).',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover', 'images', 'tags', 'genres', 'warehouses', 'stocks', 'reviews.user.profile'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books);
            });

        $this->add('select-columns', 'k2', 'Select only needed columns',
            'Same queries as (d) but narrower rows. Foreign keys must be selected too: genre_id on books for genre, and imageable_type/imageable_id on images for cover (pivot keys are added automatically).',
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->select(['id', 'title', 'genre_id'])
                    ->with([
                        'authors:id,first_name,last_name',
                        'genre:id,name',
                        'cover:id,imageable_type,imageable_id,path',
                    ])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books);
            });

        $this->add('select-missing-fk', 'k3', 'Broken: select without genre_id',
            "genre_id is not selected, so the genre relation can't be matched and is silently null for every book. With strict mode's missing-attribute check this throws MissingAttributeException instead; it is switched off here to show the silent failure.",
            function (array $ids) {
                $books = Book::whereIn('id', $ids)
                    ->select(['id', 'title']) // forgot genre_id!
                    ->with([
                        'authors:id,first_name,last_name',
                        'genre:id,name',
                        'cover:id,imageable_type,imageable_id,path',
                    ])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books);
            }, allowMissingAttributes: true, ranked: false);

        $this->add('accessor-lazy', 'l1', 'Hidden N+1 in an accessor',
            '{{ $book->author_names }} looks like a column, but the LabBook accessor reads $this->authors — one query per book.',
            function (array $ids) {
                $books = LabBook::whereIn('id', $ids)
                    ->with(['genre', 'cover'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books);
            }, lazy: true);

        $this->add('accessor-eager', 'l2', 'Accessor with its relation eager-loaded',
            'Same accessor; with(authors) makes it free.',
            function (array $ids) {
                $books = LabBook::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books);
            });

        $this->add('inverse-lazy', 'm1', 'Inverse N+1: $review->book',
            'Reviews are eager-loaded from the book, but the partial shows $review->book->title: each review loads its (already known!) book again.',
            function (array $ids) {
                $books = LabBook::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover', 'labReviews.user.profile'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books, feed: 'labReviews', showReviewBook: true);
            }, lazy: true);

        $this->add('inverse-chaperone', 'm2', 'Inverse relation with ->chaperone()',
            "hasMany(...)->chaperone('book') sets each review's book relation to the parent model after loading: no extra queries.",
            function (array $ids) {
                $books = LabBook::whereIn('id', $ids)
                    ->with(['authors', 'genre', 'cover', 'labReviewsChaperoned.user.profile'])
                    ->withCount('approvedReviews')
                    ->get();

                return $this->render($books, feed: 'labReviewsChaperoned', showReviewBook: true);
            });

        // Automatic eager loading exists since Laravel 12.8.
        if (method_exists(Collection::class, 'withRelationshipAutoloading')
            && method_exists(Model::class, 'automaticallyEagerLoadRelationships')) {
            $this->add('autoload', 'n', 'Automatic eager loading',
                'withRelationshipAutoloading(): the first time a relation is touched on one model, it is loaded for the whole collection. Works with strict mode (autoload runs before the lazy-loading check). Model::automaticallyEagerLoadRelationships() enables it globally — not used in this project.',
                function (array $ids) {
                    $books = Book::whereIn('id', $ids)->get()->withRelationshipAutoloading();

                    return $this->render($books);
                });
        }
    }
}
