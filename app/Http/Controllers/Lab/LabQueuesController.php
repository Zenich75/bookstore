<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Lab\Queues\BookIdsJob;
use App\Lab\Queues\BooksCollectionJob;
use App\Lab\Queues\BookWithoutRelationsJob;
use App\Lab\Queues\BookWithRelationsJob;
use App\Lab\Queues\FlakyJob;
use App\Lab\Queues\LabPingJob;
use App\Lab\Queues\MissingModelJob;
use App\Lab\Queues\RetryUntilJob;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * /lab/queues: one button per way of dispatching a job. Jobs log what they saw on
 * the worker into lab_job_runs; the page shows the log, pending and failed jobs.
 */
class LabQueuesController extends Controller
{
    /**
     * Buttons on the page, grouped (labels: lang/{uk,en}/lab.php => queues.*).
     */
    public const GROUPS = [
        'dispatching' => ['dispatch', 'dispatch_sync', 'dispatch_after_response', 'dispatch_if_true', 'dispatch_if_false', 'delay', 'chain', 'batch'],
        'failures' => ['flaky', 'retry_until'],
        'serialization' => ['with_relations', 'without_relations', 'without_relations_call', 'collection', 'ids', 'missing_model'],
        'transactions' => ['after_commit'],
    ];

    public function index(): View
    {
        return view('lab.queues', [
            'groups' => self::GROUPS,
            'runs' => DB::table('lab_job_runs')->latest('id')->limit(40)->get(),
            'pending' => DB::table('jobs')->selectRaw('queue, count(*) as jobs')->groupBy('queue')->pluck('jobs', 'queue'),
            'failed' => DB::table('failed_jobs')->latest('id')->limit(10)->get(['id', 'uuid', 'queue', 'payload', 'exception', 'failed_at']),
            'connection' => config('queue.default'),
        ]);
    }

    public function dispatch(string $variant): RedirectResponse
    {
        abort_unless(in_array($variant, array_merge(...array_values(self::GROUPS)), true), 404);

        $details = $this->run($variant);

        return redirect()->route('lab.queues')
            ->with('status', __('lab.queues.flash.dispatched', ['variant' => __('lab.queues.variants.'.$variant.'.title')]))
            ->with('lab_details', $details);
    }

    /**
     * queue:retry all / queue:flush from the page (the same Artisan commands).
     */
    public function failed(string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['retry', 'flush'], true), 404);

        Artisan::call($action === 'retry' ? 'queue:retry' : 'queue:flush', $action === 'retry' ? ['id' => ['all']] : []);

        return redirect()->route('lab.queues')->with('status', trim(Artisan::output()) ?: __('lab.queues.flash.done'));
    }

    /**
     * @return array<string, mixed> dispatch-side facts shown after the redirect
     */
    private function run(string $variant): array
    {
        return match ($variant) {
            // Queued on the default connection (database): a worker runs it later.
            'dispatch' => $this->sent(LabPingJob::dispatch('dispatch')),
            // Runs now, in this request, through the "sync" connection — still serialized.
            'dispatch_sync' => $this->sent(LabPingJob::dispatchSync('dispatch_sync')),
            // Runs in this PHP process after the response has been sent to the browser.
            'dispatch_after_response' => $this->sent(LabPingJob::dispatchAfterResponse('dispatch_after_response')),
            'dispatch_if_true' => $this->sent(LabPingJob::dispatchIf(true, 'dispatch_if_true')),
            // Condition false: nothing is queued at all.
            'dispatch_if_false' => $this->sent(LabPingJob::dispatchIf(false, 'dispatch_if_false')),
            'delay' => $this->sent(LabPingJob::dispatch('delay', ['dispatched_at' => now()->toTimeString()])->delay(now()->addSeconds(10))),
            // One after another; a failure stops the rest.
            'chain' => $this->sent(Bus::chain([
                new LabPingJob('chain', ['step' => 1]),
                new LabPingJob('chain', ['step' => 2]),
                new LabPingJob('chain', ['step' => 3]),
            ])->dispatch()),
            // In parallel, with shared progress (job_batches).
            'batch' => ['batch' => Bus::batch(collect(range(1, 5))->map(fn (int $i) => new LabPingJob('batch', ['n' => $i]))->all())
                ->name('Lab batch')->dispatch()->id],
            // Retries only exist on a real queue: the sync driver rethrows the first
            // exception into this request. Hence always the database connection.
            'flaky' => $this->sent(FlakyJob::dispatch()->onConnection('database')),
            'retry_until' => $this->sent(RetryUntilJob::dispatch()->onConnection('database')),
            'with_relations' => $this->measured(new BookWithRelationsJob($this->bookWithRelations())),
            'without_relations' => $this->measured(new BookWithoutRelationsJob($this->bookWithRelations())),
            // The same, decided by the caller instead of the job class.
            'without_relations_call' => $this->measured(new BookWithRelationsJob($this->bookWithRelations()->withoutRelations(), 'without_relations_call')),
            'collection' => $this->collectionVsIds(),
            'ids' => $this->measured(new BookIdsJob(Book::orderBy('id')->limit(1000)->pluck('id')->all())),
            'missing_model' => $this->missingModel(),
            'after_commit' => $this->afterCommit(),
        };
    }

    private function sent(mixed $pending): array
    {
        // PendingDispatch/PendingChain push on destruct; force it now for a clear log order.
        unset($pending);

        return [];
    }

    /**
     * Measure the serialized job before it is dispatched.
     *
     * @return array<string, int>
     */
    private function measured(object $job): array
    {
        $job->payloadBytes = strlen(serialize($job));
        dispatch($job);

        return ['payload_bytes' => $job->payloadBytes];
    }

    private function bookWithRelations(): Book
    {
        return Book::with(['genre', 'authors', 'reviews.user', 'stocks', 'tags'])->has('reviews')->orderBy('id')->firstOrFail();
    }

    /**
     * @return array<string, int>
     */
    private function collectionVsIds(): array
    {
        $books = Book::orderBy('id')->limit(1000)->get();
        $result = $this->measured(new BooksCollectionJob($books));

        // What the payload would be without SerializesModels: every attribute of every model.
        return [...$result, 'without_serializes_models_bytes' => strlen(serialize($books->all()))];
    }

    /**
     * A throwaway book, queued, then deleted before any worker can run the job.
     *
     * @return array<string, int>
     */
    private function missingModel(): array
    {
        $book = Book::create([
            'title' => 'Lab: '.Str::random(8),
            'isbn' => '999'.random_int(1_000_000_000, 9_999_999_999),
            'price' => 100,
            'format' => 'ebook',
            'genre_id' => Genre::query()->value('id'),
        ]);

        // On the sync connection the job would run right here, before the delete.
        MissingModelJob::dispatch($book);
        $book->forceDelete();

        return ['book' => $book->id];
    }

    /**
     * Dispatched inside a transaction that rolls back: with after_commit the job is
     * held until COMMIT and dropped on ROLLBACK — the worker never sees it.
     *
     * @return array<string, int>
     */
    private function afterCommit(): array
    {
        $before = DB::table('jobs')->count();

        try {
            DB::transaction(function () {
                // Explicit ->afterCommit() (the database connection already has
                // after_commit => true; this is how a single job opts in elsewhere).
                LabPingJob::dispatch('after_commit')->afterCommit();

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        return ['jobs_before' => $before, 'jobs_after' => DB::table('jobs')->count()];
    }
}
