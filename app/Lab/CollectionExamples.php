<?php

namespace App\Lab;

use App\Enums\BookFormat;
use App\Lab\Collections\Approach;
use App\Lab\Collections\Comparison;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Profile;
use App\Models\Review;
use App\Models\Stock;
use App\Reports\StockByWarehouseGenreReport;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registry of "same task, approach A vs B" comparisons for /lab/collections.
 *
 * Every approach is profiled with QueryProfiler (queries, DB time, wall time,
 * peak memory). Write comparisons run inside a transaction that is rolled back.
 */
final class CollectionExamples
{
    /**
     * @var array<string, string> section key => title
     */
    public const SECTIONS = [
        'types' => 'a. Return types',
        'eloquent-collection' => 'b. Eloquent Collection methods',
        'relation-query' => 'c. Relation query vs loaded relation',
        'aggregates' => 'd. Aggregates: SQL vs PHP',
        'large-volumes' => 'e. Large volumes',
        'chunk-pitfall' => 'f. Chunk pitfall',
        'mass-update' => 'g. Mass update',
        'subqueries' => 'h. Subqueries',
        'report' => 'i. Report: SQL vs PHP',
    ];

    /**
     * The "PHP way" stock report hydrates every stock row with its book. Above this
     * many stock rows it runs on the first warehouse only (and says so).
     */
    public const PHP_REPORT_CAP = 30_000;

    /**
     * @var list<Comparison>
     */
    private array $comparisons = [];

    private ?int $busiestBookId = null;

    private ?int $reportWarehouseId = null;

    private bool $reportCapped = false;

    public function __construct()
    {
        $this->types();
        $this->eloquentCollection();
        $this->relationQuery();
        $this->aggregates();
        $this->largeVolumes();
        $this->chunkPitfall();
        $this->massUpdate();
        $this->subqueries();
        $this->report();
    }

    /**
     * @return list<Comparison>
     */
    public function comparisons(?string $section = null): array
    {
        return array_values(array_filter(
            $this->comparisons,
            fn (Comparison $comparison) => $section === null || $comparison->section === $section,
        ));
    }

    /**
     * Run every approach of a comparison.
     *
     * @return list<array{approach: Approach, profile: Profile}>
     */
    public function run(Comparison $comparison): array
    {
        $this->prepare();

        return array_map(function (Approach $approach) use ($comparison) {
            $callback = fn () => Presenter::present(($approach->run)());

            if ($comparison->writes) {
                $inner = $callback;
                $callback = function () use ($inner) {
                    DB::beginTransaction();

                    try {
                        return $inner();
                    } finally {
                        DB::rollBack();
                    }
                };
            }

            return [
                'approach' => $approach,
                'profile' => QueryProfiler::run($callback, $comparison->heavy ? 1 : 2),
            ];
        }, $comparison->approaches);
    }

    /**
     * Notes shown above a section (e.g. when a variant runs on a capped subset).
     *
     * @return list<string>
     */
    public function notes(string $section): array
    {
        $this->prepare();

        return $section === 'report' && $this->reportCapped
            ? ['More than '.number_format(self::PHP_REPORT_CAP).' stock rows: both approaches run on the first warehouse only, so the PHP way fits in memory.']
            : [];
    }

    /**
     * Sample records, resolved once and outside profiling.
     */
    private function prepare(): void
    {
        if ($this->busiestBookId !== null) {
            return;
        }

        $this->busiestBookId = (int) Review::query()->select('book_id')->groupBy('book_id')
            ->orderByRaw('count(*) desc')->orderBy('book_id')->value('book_id');

        if (Stock::count() > self::PHP_REPORT_CAP) {
            $this->reportCapped = true;
            $this->reportWarehouseId = (int) DB::table('warehouses')->min('id');
        }
    }

    /**
     * @param  list<Approach>  $approaches
     */
    private function compare(string $section, string $title, string $description, array $approaches, bool $writes = false, bool $heavy = false): void
    {
        $this->comparisons[] = new Comparison($section, $title, $description, $approaches, $writes, $heavy);
    }

    private static function approach(string $label, Closure $run, ?string $note = null): Approach
    {
        return new Approach($label, $run, $note);
    }

    /**
     * Type of a value as shown on the page: "string 'paperback'", "App\Enums\BookFormat::Paperback", ...
     */
    private static function typed(mixed $value): string
    {
        return match (true) {
            $value instanceof \UnitEnum => $value::class.'::'.$value->name,
            $value instanceof \DateTimeInterface => $value::class.' '.$value->format('Y-m-d H:i'),
            is_string($value) => 'string '.var_export(Str::limit($value, 40), true),
            default => get_debug_type($value).' '.var_export($value, true),
        };
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return array<string, mixed>
     */
    private static function describe(Collection $rows): array
    {
        $first = $rows->first();

        return [
            'collection' => $rows::class,
            'count' => $rows->count(),
            'item' => get_debug_type($first),
            'format' => self::typed($first->format),
            'price' => self::typed($first->price),
            'published_at' => self::typed($first->published_at),
            'meta' => self::typed($first->meta),
            'has relation methods' => method_exists($first, 'genre'),
            'genre loaded' => $first instanceof Book ? $first->relationLoaded('genre') : 'n/a (no relations)',
        ];
    }

    private function types(): void
    {
        $this->compare('types', 'What comes back',
            'The same three rows read three ways. DB::table() returns stdClass with raw column values; Eloquent returns models with casts (enum, Carbon, array) and relation methods, but no relation is loaded until you ask; toBase() runs the Eloquent query (soft-delete scope included) and skips hydration. DB::table() knows nothing about soft deletes.',
            [
                self::approach('DB::table()->get()', function () {
                    return self::describe(DB::table('books')->whereNotNull('published_at')->orderBy('id')->limit(3)->get());
                }),
                self::approach('Book::get()', function () {
                    return self::describe(Book::whereNotNull('published_at')->orderBy('id')->limit(3)->get());
                }),
                self::approach('Book::toBase()->get()', function () {
                    return self::describe(Book::whereNotNull('published_at')->orderBy('id')->limit(3)->toBase()->get());
                }),
            ]);

        $this->compare('types', 'Hydration cost: every book',
            'One query each; the difference is what PHP builds from the rows. Eloquent creates a model per row (attributes, original copy, casts on access), which costs time and memory.',
            [
                self::approach('DB::table()->get()', function () {
                    $rows = DB::table('books')->get();

                    return ['count' => $rows->count(), 'item' => get_debug_type($rows->first())];
                }),
                self::approach('Book::get()', function () {
                    $books = Book::get();

                    return ['count' => $books->count(), 'item' => get_debug_type($books->first())];
                }),
                self::approach('Book::toBase()->get()', function () {
                    $rows = Book::toBase()->get();

                    return ['count' => $rows->count(), 'item' => get_debug_type($rows->first())];
                }),
            ], heavy: true);
    }

    private function eloquentCollection(): void
    {
        $this->compare('eloquent-collection', 'load / loadMissing / loadCount vs per-model queries',
            'Eloquent Collection methods eager-load for every model at once; doing it per model is an N+1 in disguise.',
            [
                self::approach('Collection methods', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    $books->load('genre')->loadMissing(['genre', 'cover'])->loadCount('reviews');

                    return $books->pluck('reviews_count', 'id');
                }),
                self::approach('Per model', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    $books->each(function (Book $book) {
                        $book->setRelation('genre', $book->genre()->first());
                        $book->setRelation('cover', $book->cover()->first());
                        $book->reviews_count = $book->reviews()->count();
                    });

                    return $books->pluck('reviews_count', 'id');
                }),
            ]);

        $this->compare('eloquent-collection', 'find() / modelKeys() vs base collection',
            'Eloquent Collection knows primary keys: find($id) and modelKeys(). On a base collection you spell it out.',
            [
                self::approach('Eloquent Collection', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    return ['found' => $books->find($books->last()->id)?->title, 'keys' => $books->modelKeys()];
                }),
                self::approach('Base Collection', function () {
                    $books = Book::orderBy('id')->limit(20)->get()->toBase();

                    return ['found' => $books->firstWhere('id', $books->last()->id)?->title, 'keys' => $books->pluck('id')->all()];
                }),
            ]);

        $this->compare('eloquent-collection', 'fresh() vs map->fresh()',
            'Reloading models: the collection does it with one whereIn query, per model it is one query each.',
            [
                self::approach('$books->fresh()', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    return $books->fresh()->count();
                }),
                self::approach('$books->map->fresh()', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    return $books->map->fresh()->count();
                }),
            ]);

        $this->compare('eloquent-collection', 'toQuery()->update()',
            'toQuery() turns a collection back into a query over its keys: one UPDATE ... WHERE id IN (...). Identical to building the whereIn by hand.',
            [
                self::approach('$books->toQuery()->update()', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    return $books->toQuery()->update(['published_year' => 2000]);
                }),
                self::approach('whereIn(modelKeys())->update()', function () {
                    $books = Book::orderBy('id')->limit(20)->get();

                    return Book::whereIn('id', $books->modelKeys())->update(['published_year' => 2000]);
                }),
            ], writes: true);

        $this->compare('eloquent-collection', 'Comparing by key vs by value: diff / intersect / unique / contains',
            'Eloquent Collection compares models by primary key. A base collection compares by value (diff/intersect cast models to JSON, unique/contains use ==), so an in-memory change or a different instance of the same row counts as "different". Books 6–10 are in both sets; book 6 has an unsaved price change in the second set.',
            [
                self::approach('Eloquent Collection (by key)', function () {
                    $first = Book::orderBy('id')->limit(10)->get();
                    $second = Book::orderBy('id')->offset(5)->limit(10)->get();
                    $second->first()->price += 1; // in memory only

                    return [
                        'diff' => $first->diff($second)->modelKeys(),
                        'intersect' => $first->intersect($second)->modelKeys(),
                        'unique' => $first->concat($second)->unique()->count(),
                        'contains' => $first->contains(Book::find($first->first()->id)),
                    ];
                }),
                self::approach('Base Collection (by value)', function () {
                    $first = Book::orderBy('id')->limit(10)->get()->toBase();
                    $second = Book::orderBy('id')->offset(5)->limit(10)->get()->toBase();
                    $second->first()->price += 1; // in memory only

                    return [
                        'diff' => $first->diff($second)->pluck('id')->all(),
                        'intersect' => $first->intersect($second)->pluck('id')->all(),
                        'unique' => $first->concat($second)->unique()->count(),
                        'contains' => $first->contains(Book::find($first->first()->id)),
                    ];
                }),
            ]);

        $this->compare('eloquent-collection', 'makeVisible / makeHidden vs mapping arrays',
            'Profile hides phone via #[Hidden]. makeVisible()/makeHidden() change serialization for the whole collection.',
            [
                self::approach('makeVisible / makeHidden', function () {
                    $profiles = Profile::orderBy('id')->limit(3)->get();

                    return $profiles->makeVisible('phone')->makeHidden(['bio', 'avatar_path', 'created_at', 'updated_at'])->toArray();
                }),
                self::approach('map + Arr::except', function () {
                    $profiles = Profile::orderBy('id')->limit(3)->get();

                    return $profiles->map(fn (Profile $profile) => Arr::except(
                        [...$profile->toArray(), 'phone' => $profile->phone],
                        ['bio', 'avatar_path', 'created_at', 'updated_at'],
                    ))->all();
                }),
            ]);

        $this->compare('eloquent-collection', 'map() keeps the Eloquent Collection only while it returns models',
            'When the callback returns something other than models, you get a base Illuminate\Support\Collection — and lose load(), modelKeys(), toQuery()... (pluck/keys/... behave the same).',
            [
                self::approach('map() returning models', function () {
                    $books = Book::orderBy('id')->limit(5)->get();
                    $mapped = $books->map(fn (Book $book) => $book->setAttribute('title', Str::upper($book->title)));

                    return ['before' => $books::class, 'after' => $mapped::class];
                }),
                self::approach('map() returning strings', function () {
                    $books = Book::orderBy('id')->limit(5)->get();
                    $mapped = $books->map(fn (Book $book) => $book->title);

                    return ['before' => $books::class, 'after' => $mapped::class];
                }),
            ]);
    }

    private function relationQuery(): void
    {
        $this->compare('relation-query', 'Count with a condition',
            '$book->reviews() is a query builder (COUNT in SQL); $book->reviews is a loaded collection (every review hydrated, then filtered in PHP). Sample: the book with most reviews.',
            [
                self::approach('reviews()->where()->count()', function () {
                    $book = Book::find($this->busiestBookId);

                    return $book->reviews()->where('rating', 5)->count();
                }),
                self::approach('reviews->where()->count()', function () {
                    $book = Book::find($this->busiestBookId)->load('reviews');

                    return $book->reviews->where('rating', 5)->count();
                }),
            ]);

        $this->compare('relation-query', 'exists() vs count() > 0',
            'Both are one query; EXISTS can stop at the first matching row, COUNT has to count them all.',
            [
                self::approach('exists()', function () {
                    $book = Book::find($this->busiestBookId);

                    return $book->reviews()->where('rating', 1)->exists();
                }),
                self::approach('count() > 0', function () {
                    $book = Book::find($this->busiestBookId);

                    return $book->reviews()->where('rating', 1)->count() > 0;
                }),
            ]);

        $this->compare('relation-query', 'firstWhere on the query vs on the collection',
            'On the relation query, firstWhere() is where()->first(): LIMIT 1 in SQL. On the loaded relation it filters in PHP after loading everything.',
            [
                self::approach('reviews()->firstWhere()', function () {
                    $book = Book::find($this->busiestBookId);

                    return $book->reviews()->firstWhere('rating', 5)?->only(['id', 'rating']);
                }),
                self::approach('reviews->where()->first()', function () {
                    $book = Book::find($this->busiestBookId)->load('reviews');

                    return $book->reviews->where('rating', 5)->first()?->only(['id', 'rating']);
                }),
            ]);
    }

    private function aggregates(): void
    {
        $this->compare('aggregates', 'avg / sum / max price',
            'The database returns three numbers; the PHP way transfers and hydrates every price first.',
            [
                self::approach('SQL', function () {
                    return (array) Book::toBase()
                        ->selectRaw('avg(price) as avg_price, sum(price) as total_price, max(price) as max_price')
                        ->first();
                }),
                self::approach('Collection', function () {
                    $books = Book::get(['price']);

                    return [
                        'avg_price' => $books->avg('price'),
                        'total_price' => $books->sum('price'),
                        'max_price' => $books->max('price'),
                    ];
                }),
            ], heavy: true);

        $this->compare('aggregates', 'Count by format',
            'GROUP BY in SQL vs groupBy() on a collection.',
            [
                self::approach('SQL groupBy', function () {
                    return Book::toBase()
                        ->select('format')
                        ->selectRaw('count(*) as total')
                        ->groupBy('format')
                        ->orderBy('format')
                        ->pluck('total', 'format');
                }),
                self::approach('Collection groupBy', function () {
                    return Book::get(['format'])
                        ->groupBy(fn (Book $book) => $book->format->value)
                        ->map->count()
                        ->sortKeys();
                }),
            ], heavy: true);

        $this->compare('aggregates', 'pluck in SQL vs on a collection',
            'Book::pluck() selects two columns; get()->pluck() loads whole models and throws most of it away.',
            [
                self::approach('Book::pluck()', function () {
                    return Book::pluck('title', 'id')->take(3);
                }),
                self::approach('Book::get()->pluck()', function () {
                    return Book::get()->pluck('title', 'id')->take(3);
                }),
            ], heavy: true);
    }

    private function largeVolumes(): void
    {
        $this->compare('large-volumes', 'Process every book: sum the price of all ebooks',
            "Each approach touches every book model. chunk/chunkById run one query per 1000 rows and keep only one chunk in memory. lazy/lazyById do the same behind a LazyCollection. cursor() runs one query and hydrates one model at a time — but it cannot eager-load (relations are loaded per page of parents, and cursor has no pages), and with MySQL's default buffered queries the driver still receives the whole result set into PHP memory before the first row is yielded, so the saving is limited to not hydrating everything at once. The pipeline stops after 10 matches, yet the query has already transferred every row. Numbers below are as measured — single cold run each.",
            [
                self::approach('get() (baseline)', function () {
                    return Book::get()->where('format', BookFormat::Ebook)->sum('price');
                }),
                self::approach('chunk(1000)', function () {
                    $sum = 0;
                    Book::chunk(1000, function ($books) use (&$sum) {
                        $sum += $books->where('format', BookFormat::Ebook)->sum('price');
                    });

                    return $sum;
                }),
                self::approach('chunkById(1000)', function () {
                    $sum = 0;
                    Book::chunkById(1000, function ($books) use (&$sum) {
                        $sum += $books->where('format', BookFormat::Ebook)->sum('price');
                    });

                    return $sum;
                }),
                self::approach('lazy(1000)', function () {
                    $sum = 0;
                    foreach (Book::lazy(1000) as $book) {
                        $sum += $book->format === BookFormat::Ebook ? $book->price : 0;
                    }

                    return $sum;
                }),
                self::approach('lazyById(1000)', function () {
                    $sum = 0;
                    foreach (Book::lazyById(1000) as $book) {
                        $sum += $book->format === BookFormat::Ebook ? $book->price : 0;
                    }

                    return $sum;
                }),
                self::approach('cursor()', function () {
                    $sum = 0;
                    foreach (Book::cursor() as $book) {
                        $sum += $book->format === BookFormat::Ebook ? $book->price : 0;
                    }

                    return $sum;
                }),
                self::approach('cursor() pipeline: filter → map → take(10)', function () {
                    return Book::cursor()
                        ->filter(fn (Book $book) => $book->format === BookFormat::Ebook)
                        ->map(fn (Book $book) => $book->title)
                        ->take(10)
                        ->all();
                }, 'Different task (first 10 ebook titles): shows that the LazyCollection stops early.'),
            ], heavy: true);
    }

    private function chunkPitfall(): void
    {
        $this->compare('chunk-pitfall', 'Approve all unapproved reviews in chunks',
            'chunk() pages with LIMIT/OFFSET. Updating rows so they no longer match the WHERE shifts the remaining rows: page 2 starts a whole page too far and skips a chunk every time. chunkById() pages with WHERE id > last_id, so changing the filtered column is safe. (Rolled back.)',
            [
                self::approach('chunk(100)', function () {
                    $before = Review::where('is_approved', false)->count();

                    $chunks = 0;
                    Review::where('is_approved', false)->chunk(100, function ($reviews) use (&$chunks) {
                        $reviews->toQuery()->update(['is_approved' => true]);
                        $chunks++;
                    });

                    return ['unapproved_before' => $before, 'chunks' => $chunks, 'unapproved_after' => Review::where('is_approved', false)->count()];
                }),
                self::approach('chunkById(100)', function () {
                    $before = Review::where('is_approved', false)->count();

                    $chunks = 0;
                    Review::where('is_approved', false)->chunkById(100, function ($reviews) use (&$chunks) {
                        $reviews->toQuery()->update(['is_approved' => true]);
                        $chunks++;
                    });

                    return ['unapproved_before' => $before, 'chunks' => $chunks, 'unapproved_after' => Review::where('is_approved', false)->count()];
                }),
            ], writes: true, heavy: true);
    }

    private function massUpdate(): void
    {
        $this->compare('mass-update', 'Raise the price of 200 books by $1',
            'Eloquent query updates set updated_at automatically; DB::table() does not; save() per model costs one UPDATE each (and fires model events — observers are stage 6). (Rolled back.)',
            [
                self::approach('$books->toQuery()->increment()', function () {
                    $books = Book::orderBy('id')->limit(200)->get();
                    $before = $books->pluck('updated_at', 'id');

                    $books->toQuery()->increment('price', 100);

                    return $this->updatedAtChanges($before);
                }),
                self::approach('DB::table()->increment()', function () {
                    $books = Book::orderBy('id')->limit(200)->get();
                    $before = $books->pluck('updated_at', 'id');

                    DB::table('books')->whereIn('id', $books->modelKeys())->increment('price', 100);

                    return $this->updatedAtChanges($before);
                }),
                self::approach('each->save()', function () {
                    $books = Book::orderBy('id')->limit(200)->get();
                    $before = $books->pluck('updated_at', 'id');

                    $books->each(function (Book $book) {
                        $book->price += 100;
                        $book->save();
                    });

                    return $this->updatedAtChanges($before);
                }),
            ], writes: true);
    }

    /**
     * @param  Collection<int, mixed>  $before  id => updated_at
     * @return array{rows: int, updated_at_changed: int}
     */
    private function updatedAtChanges(Collection $before): array
    {
        $after = Book::whereKey($before->keys())->pluck('updated_at', 'id');

        return [
            'rows' => $after->count(),
            'updated_at_changed' => $after->filter(fn ($value, $id) => (string) $value !== (string) $before[$id])->count(),
        ];
    }

    private function subqueries(): void
    {
        $this->compare('subqueries', 'Latest review date per book',
            'addSelect() with a correlated subquery returns one extra column in the same query; eager-loading latestReview runs a second query and hydrates a Review per book. The subquery wins when you need a value, the relation when you need the model.',
            [
                self::approach('addSelect(subquery)', function () {
                    return Book::addSelect([
                        'latest_review_at' => Review::select('created_at')
                            ->whereColumn('reviews.book_id', 'books.id')
                            ->latest()
                            ->limit(1),
                    ])->orderBy('id')->limit(20)->get()->pluck('latest_review_at', 'id');
                }),
                self::approach("with('latestReview')", function () {
                    return Book::with('latestReview')->orderBy('id')->limit(20)->get()
                        ->mapWithKeys(fn (Book $book) => [$book->id => $book->latestReview?->created_at]);
                }),
            ]);

        $this->compare('subqueries', 'whereExists vs whereHas',
            'Books with at least one 5-star approved review. whereHas() generates the same EXISTS subquery you would write by hand with whereExists() — compare the SQL.',
            [
                self::approach('whereExists()', function () {
                    return Book::whereExists(fn (QueryBuilder $query) => $query
                        ->select(DB::raw(1))
                        ->from('reviews')
                        ->whereColumn('reviews.book_id', 'books.id')
                        ->where('reviews.rating', 5)
                        ->where('reviews.is_approved', true))->count();
                }),
                self::approach('whereHas()', function () {
                    return Book::whereHas('approvedReviews', fn ($query) => $query->where('rating', 5))->count();
                }),
            ]);
    }

    private function report(): void
    {
        $this->compare('report', 'Stock by warehouse and root genre',
            'The same report as /reports/stock. SQL: joins + GROUP BY return a few dozen rows. PHP: hydrate every stock row with its book, then groupBy/sum on collections.',
            [
                self::approach('SQL (StockByWarehouseGenreReport)', function () {
                    $rows = (new StockByWarehouseGenreReport($this->reportWarehouseId))->rows();

                    return ['groups' => $rows->count(), 'quantity' => $rows->sum('quantity'), 'value_cents' => $rows->sum('value_cents'), 'first' => $rows->first()];
                }),
                self::approach('PHP (models + collections)', function () {
                    $genres = Genre::get(['id', 'name', 'parent_id'])->keyBy('id');

                    $rows = Stock::query()
                        ->when($this->reportWarehouseId, fn ($query, $id) => $query->where('warehouse_id', $id))
                        ->whereHas('book')
                        ->with(['warehouse:id,name', 'book:id,genre_id,price'])
                        ->get()
                        ->groupBy(function (Stock $stock) use ($genres) {
                            $genre = $genres[$stock->book->genre_id];

                            return $stock->warehouse->name.' / '.$genres[$genre->parent_id ?? $genre->id]->name;
                        })
                        ->map(fn (Collection $stocks) => [
                            'quantity' => $stocks->sum('quantity'),
                            'reserved' => $stocks->sum('reserved'),
                            'books' => $stocks->pluck('book_id')->unique()->count(),
                            'value_cents' => $stocks->sum(fn (Stock $stock) => $stock->quantity * $stock->book->price),
                        ])
                        ->sortKeys();

                    return ['groups' => $rows->count(), 'quantity' => $rows->sum('quantity'), 'value_cents' => $rows->sum('value_cents'), 'first' => $rows->first()];
                }),
            ], heavy: true);
    }
}
