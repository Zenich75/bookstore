<?php

namespace App\Lab;

use App\Enums\StockMovementReason;
use App\Lab\Models\LabBook;
use App\Lab\Models\LabUser;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Profile;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\ValueObjects\Money;
use Closure;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Registry of runnable model-layer examples for /lab/models (stage 6): Money cast,
 * accessors/mutators, casts, serialization, scopes, events, mass assignment.
 *
 * Each example shows its code, result and SQL. Write examples run inside a
 * transaction that is always rolled back.
 */
final class ModelExamples
{
    /**
     * Section keys in display order; titles: lang/{en,uk}/lab.php => models.sections.*
     *
     * @var list<string>
     */
    public const SECTIONS = ['money', 'accessors', 'casts', 'serialization', 'scopes', 'events', 'mass-assignment'];

    /**
     * @var list<Example>
     */
    private array $examples = [];

    public function __construct()
    {
        $this->money();
        $this->accessors();
        $this->casts();
        $this->serialization();
        $this->scopes();
        $this->events();
        $this->massAssignment();
    }

    /**
     * @return list<array{section: string, title: string, type: string, code: string, writes: bool, result: mixed, error: string|null, queries: list<string>}>
     */
    public function run(): array
    {
        return array_map(fn (Example $example) => $this->runOne($example), $this->examples);
    }

    /**
     * @return array{section: string, title: string, type: string, code: string, writes: bool, result: mixed, error: string|null, queries: list<string>}
     */
    private function runOne(Example $example): array
    {
        $result = null;
        $error = null;

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        if ($example->writes) {
            $connection->beginTransaction();
        }

        try {
            $result = Presenter::present(($example->run)());
        } catch (Throwable $e) {
            $error = class_basename($e).': '.$e->getMessage();
        } finally {
            if ($example->writes) {
                $connection->rollBack();
            }

            $queries = array_map(fn (array $query) => self::interpolate($query['query'], $query['bindings']), $connection->getQueryLog());
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        return [
            'section' => $example->section,
            'title' => $example->title,
            'type' => $example->type,
            'code' => $example->code(),
            'writes' => $example->writes,
            'result' => $result,
            'error' => $error,
            'queries' => $queries,
        ];
    }

    /**
     * SQL with bindings inlined, for display only.
     *
     * @param  array<int, mixed>  $bindings
     */
    private static function interpolate(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = match (true) {
                $binding === null => 'null',
                is_bool($binding) => $binding ? '1' : '0',
                is_int($binding), is_float($binding) => (string) $binding,
                $binding instanceof \DateTimeInterface => "'".$binding->format('Y-m-d H:i:s')."'",
                default => "'".Str::limit((string) $binding, 60)."'",
            };
            $sql = preg_replace('/\?/', str_replace(['\\', '$'], ['\\\\', '\\$'], $value), $sql, 1);
        }

        return $sql;
    }

    /**
     * @param  Closure(): mixed  $run
     */
    private function add(string $section, string $titleKey, string $type, Closure $run, bool $writes = false): void
    {
        $this->examples[] = new Example(__('lab.models.sections.'.$section), __('lab.models.examples.'.$titleKey), $type, $run, $writes);
    }

    /**
     * "App\ValueObjects\Money 199,99 ₴", "int 19999", "Carbon\CarbonImmutable 2001-02-03 04:05" ...
     */
    private static function typed(mixed $value): string
    {
        return match (true) {
            $value instanceof Money => $value::class.' '.$value->format(),
            $value instanceof \UnitEnum => $value::class.'::'.$value->name,
            $value instanceof \DateTimeInterface => $value::class.' '.$value->format('Y-m-d H:i'),
            is_object($value) => $value::class.' '.json_encode($value, JSON_UNESCAPED_UNICODE),
            is_string($value) => 'string '.var_export(Str::limit($value, 60), true),
            default => get_debug_type($value).' '.var_export($value, true),
        };
    }

    private function money(): void
    {
        $this->add('money', 'money_arithmetic', 'value object', function () {
            $price = Money::fromUah('199.99');
            $total = $price->multiply(3)->add(Money::fromCents(1));

            return [
                'price' => self::typed($price),
                'total_cents' => $total->cents,
                'total_uah' => $total->toUah(),
                'total_formatted' => $total->format(),
                'json' => json_encode($total, JSON_UNESCAPED_UNICODE),
                'float_trap' => var_export(0.1 + 0.2 === 0.3, true),
            ];
        });

        $this->add('money', 'money_cast', 'Castable → MoneyCast', function () {
            $book = Book::orderBy('id')->first();

            return [
                'raw (DB::table)' => self::typed(DB::table('books')->where('id', $book->id)->value('price')),
                'model ($book->price)' => self::typed($book->price),
                'getAttributes()[price]' => self::typed($book->getAttributes()['price']),
            ];
        });

        $this->add('money', 'money_set', 'set(): Money|int, float rejected', function () {
            $book = Book::orderBy('id')->first();
            $book->price = Money::fromUah('249.50');
            $book->save();

            try {
                $book->price = 19.99;
            } catch (InvalidArgumentException $e) {
                $rejected = $e->getMessage();
            }

            return [
                'saved_raw' => DB::table('books')->where('id', $book->id)->value('price'),
                'float' => $rejected ?? null,
            ];
        }, writes: true);
    }

    private function accessors(): void
    {
        $this->add('accessors', 'full_name', 'Attribute::get', function () {
            $author = Author::orderBy('id')->first(['id', 'first_name', 'last_name']);

            return $author->only(['first_name', 'last_name', 'full_name']);
        });

        $this->add('accessors', 'isbn', 'Attribute::set + get', function () {
            $book = new Book;
            $book->isbn = '978-617-7489-12-4';

            return [
                'stored' => $book->getAttributes()['isbn'],
                'isbn' => $book->isbn,
                'isbn_formatted' => $book->isbn_formatted,
            ];
        });

        $this->add('accessors', 'should_cache', 'shouldCache()', function () {
            $book = Book::orderBy('id')->first();
            $first = $book->isbn_formatted;

            $book->isbn = '9790000000001';
            $stale = $book->isbn_formatted; // still the cached value

            $book->setRawAttributes($book->getAttributes()); // clears accessor caches

            return ['first' => $first, 'isbn' => $book->isbn, 'after_change' => $stale, 'after_reset' => $book->isbn_formatted];
        });

        $this->add('accessors', 'legacy', 'getXAttribute / setXAttribute', function () {
            $book = LabBook::orderBy('id')->first();
            $book->title = "  Тіні   забутих\tпредків ";

            return ['title' => $book->title, 'display_title' => $book->display_title];
        });

        $this->add('accessors', 'available', 'Attribute::get (stage 2)', function () {
            return Stock::where('reserved', '>', 0)->orderBy('id')->first()?->only(['quantity', 'reserved', 'available']);
        });
    }

    private function casts(): void
    {
        $this->add('casts', 'raw_vs_model', '$casts', function () {
            $book = Book::whereNotNull('published_at')->orderBy('id')->first();
            $raw = DB::table('books')->where('id', $book->id)->first();

            return collect(['price', 'format', 'published_at', 'meta'])->mapWithKeys(fn (string $column) => [
                $column => ['raw' => self::typed($raw->{$column}), 'model' => self::typed($book->{$column})],
            ])->all();
        });

        $this->add('casts', 'immutable', 'datetime vs immutable_datetime', function () {
            $author = Author::whereNotNull('birth_date')->orderBy('id')->first();
            $book = Book::whereNotNull('published_at')->orderBy('id')->first();

            $born = $author->birth_date;      // Carbon (date cast)
            $born->addYear();                 // changes $born itself
            $published = $book->published_at; // CarbonImmutable
            $published->addYear();            // returns a copy, $published unchanged

            return [
                'date: variable changed' => ! $born->equalTo($author->birth_date),
                'immutable: variable changed' => ! $published->equalTo($book->published_at),
            ];
        });

        $this->add('casts', 'collection_vs_array_object', 'AsCollection / AsArrayObject', function () {
            $book = Book::whereNotNull('meta')->orderBy('id')->first();
            $book->meta->put('lab', true);

            $labBook = LabBook::find($book->id);
            $labBook->meta['lab'] = true;

            return [
                'Book::meta' => get_debug_type($book->meta),
                'Book dirty' => $book->isDirty('meta'),
                'LabBook::meta' => get_debug_type($labBook->meta),
                'LabBook dirty' => $labBook->isDirty('meta'),
            ];
        });

        $this->add('casts', 'encrypted', 'encrypted', function () {
            $profile = Profile::whereNotNull('phone')->orderBy('id')->first();

            return [
                'raw (DB::table)' => Str::limit(DB::table('profiles')->where('id', $profile->id)->value('phone'), 70),
                'model' => $profile->phone,
                'where(phone, …)->exists()' => Profile::where('phone', $profile->phone)->exists(),
            ];
        });
    }

    private function serialization(): void
    {
        $this->add('serialization', 'book_to_array', '$appends + Money', function () {
            return Book::orderBy('id')->first(['id', 'title', 'isbn', 'price', 'format', 'published_at'])->toArray();
        });

        $this->add('serialization', 'user_hidden', '#[Hidden] / makeVisible / makeHidden', function () {
            $user = User::orderBy('id')->first();

            return [
                'toArray' => array_keys($user->toArray()),
                'makeVisible(email)' => array_keys($user->makeVisible('email')->toArray()),
                'makeHidden(timestamps)' => $user->makeHidden(['created_at', 'updated_at', 'email_verified_at'])->toArray(),
            ];
        });

        $this->add('serialization', 'to_json', 'toJson()', function () {
            return Book::orderBy('id')->first(['id', 'isbn', 'price'])->toJson(JSON_UNESCAPED_UNICODE);
        });
    }

    private function scopes(): void
    {
        $this->add('scopes', 'local_chain', '#[Scope]', function () {
            $query = Book::published()
                ->inStock()
                ->ofGenre(Genre::whereNull('parent_id')->orderBy('id')->value('slug'))
                ->sorted('price_asc');

            return ['sql' => $query->toRawSql(), 'count' => $query->count(), 'cheapest' => $query->first(['id', 'title', 'price'])];
        });

        $this->add('scopes', 'by_author_search', '#[Scope] with arguments', function () {
            $book = Book::has('authors')->orderBy('id')->first(['id', 'title', 'isbn']);
            $author = $book->authors()->first();

            return [
                'byAuthor(slug)' => Book::byAuthor($author->slug)->count(),
                'byAuthor(model)' => Book::byAuthor($author)->count(),
                'search(isbn with dashes)' => Book::search($book->isbn_formatted)->pluck('id')->all(),
                'search(title word)' => Book::search(Str::before($book->title, ' '))->count(),
            ];
        });

        $this->add('scopes', 'legacy_scope', 'scopeCheaperThan()', function () {
            $query = LabBook::cheaperThan(Money::fromUah(150));

            return ['sql' => $query->toRawSql(), 'count' => $query->count()];
        });

        $this->add('scopes', 'global_scope', '#[ScopedBy(ApprovedScope)]', function () {
            return [
                'Review::count()' => Review::count(),
                'withoutGlobalScope(ApprovedScope)' => Review::withoutGlobalScope(ApprovedScope::class)->count(),
                'pending' => Review::withoutGlobalScope(ApprovedScope::class)->where('is_approved', false)->count(),
                'sql' => Review::query()->toRawSql(),
            ];
        });

        $this->add('scopes', 'soft_deletes', 'SoftDeletes (built-in global scope)', function () {
            Book::orderBy('id')->first()->delete();

            return [
                'Book::count()' => Book::count(),
                'withTrashed()' => Book::withTrashed()->count(),
                'onlyTrashed()' => Book::onlyTrashed()->count(),
                'sql' => Book::query()->toRawSql(),
            ];
        }, writes: true);
    }

    private function events(): void
    {
        $this->add('events', 'slug', 'trait boot: HasSlug', function () {
            $original = Book::orderBy('id')->first();
            $copy = fn () => tap($original->replicate(['slug', 'isbn']), function (Book $book) {
                $book->isbn = '979'.random_int(1_000_000_000, 9_999_999_999);
                $book->save(); // "creating" event => HasSlug fills the slug from the title
            });

            $first = $copy();
            $second = $copy(); // same title: the slug is taken => "-2"

            $second->update(['title' => 'Зовсім нова назва']);

            return ['first' => $first->slug, 'second' => $second->slug, 'second_renamed_to' => $second->title];
        }, writes: true);

        $this->add('events', 'observer', '#[ObservedBy(StockObserver)]', function () {
            $stock = Stock::where('quantity', '>', 0)->orderBy('id')->first();

            $stock->movementReason = StockMovementReason::Sale;
            $stock->quantity -= 1;
            $stock->save();

            return StockMovement::where('book_id', $stock->book_id)->latest('id')->first(['id', 'warehouse_id', 'book_id', 'user_id', 'delta', 'reason']);
        }, writes: true);

        $this->add('events', 'pivot_operations', 'attach / updateExistingPivot / sync', function () {
            $warehouse = Warehouse::orderBy('id')->first();
            $book = Book::whereDoesntHave('warehouses', fn ($q) => $q->whereKey($warehouse->id))->orderBy('id')->first();
            $movements = fn () => StockMovement::where('book_id', $book->id)->count();

            $before = $movements();
            $warehouse->books()->attach($book, ['quantity' => 5]);
            $afterAttach = $movements();
            $warehouse->books()->updateExistingPivot($book->id, ['quantity' => 8]);
            $afterUpdate = $movements();
            $warehouse->books()->syncWithoutDetaching([$book->id => ['quantity' => 6]]);
            $afterSync = $movements();

            return [
                'attach' => $afterAttach - $before,
                'updateExistingPivot' => $afterUpdate - $afterAttach,
                'sync (existing row)' => $afterSync - $afterUpdate,
                'deltas' => StockMovement::where('book_id', $book->id)->orderBy('id')->pluck('delta')->slice($before)->values(),
            ];
        }, writes: true);

        $this->add('events', 'bypass', 'query update: no events', function () {
            $stock = Stock::orderBy('id')->first();
            $count = fn () => StockMovement::count();

            $before = $count();
            Stock::whereKey($stock->id)->update(['quantity' => $stock->quantity + 10]);
            Stock::whereKey($stock->id)->get()->toQuery()->update(['quantity' => $stock->quantity + 20]);
            DB::table('stocks')->where('id', $stock->id)->increment('quantity');

            return ['quantity_now' => $stock->fresh()->quantity, 'movements_created' => $count() - $before];
        }, writes: true);

        $this->add('events', 'quietly', 'saveQuietly / withoutEvents', function () {
            $stock = Stock::orderBy('id')->first();
            $count = fn () => StockMovement::count();

            $before = $count();
            $stock->quantity += 1;
            $stock->saveQuietly();

            Stock::withoutEvents(function () use ($stock) {
                $stock->increment('quantity');
            });

            $stock->quantity += 1;
            $stock->save(); // events again

            return ['movements_created' => $count() - $before];
        }, writes: true);
    }

    private function massAssignment(): void
    {
        $this->add('mass-assignment', 'strict_exception', 'preventSilentlyDiscardingAttributes', function () {
            try {
                $book = new Book(['title' => 'Кобзар', 'rating_avg' => 5]);
            } catch (MassAssignmentException $e) {
                return ['exception' => class_basename($e), 'message' => $e->getMessage()];
            }

            // MODEL_STRICT=false: no exception, rating_avg silently discarded.
            return ['exception' => null, 'attributes' => $book->getAttributes()];
        });

        $this->add('mass-assignment', 'privilege_escalation', 'guarded = [] vs #[Fillable]', function () {
            // What a sign-up form could receive if someone adds a field in DevTools:
            $input = ['name' => 'Mallory', 'email' => 'mallory@example.com', 'password' => 'secret', 'role' => 'admin'];

            $labUser = LabUser::create($input);

            try {
                // MODEL_STRICT=false: created, role silently discarded (DB default "reader").
                $user = User::create([...$input, 'email' => 'mallory2@example.com'])->fresh()->role;
            } catch (MassAssignmentException $e) {
                $user = class_basename($e).': '.$e->getMessage();
            }

            return ['LabUser (guarded = [])' => $labUser->role, 'User (#[Fillable] without role)' => $user];
        }, writes: true);
    }
}
