<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportBooksRequest;
use App\Imports\BookCsv;
use App\Jobs\CheckLowStock;
use App\Jobs\ImportBooksChunk;
use App\Jobs\SendImportReport;
use App\Jobs\ValidateImportFile;
use App\Models\Import;
use App\Models\Warehouse;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CSV import of books + stock into a warehouse (admin area).
 */
class ImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.index', [
            'imports' => Import::with(['user:id,name', 'warehouse:id,name'])->latest('id')->paginate(20),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'columns' => Import::COLUMNS,
        ]);
    }

    /**
     * Upload, then hand everything to the queue:
     *
     *   chain: ValidateImportFile → batch[ImportBooksChunk × N] → CheckLowStock → SendImportReport
     *
     * A chain runs its links one after another and stops at the first failure; the
     * batch inside it runs its chunks in parallel (as many as there are workers).
     * allowFailures(): a failed chunk does not cancel the other chunks, and the chain
     * still goes on (a chained batch continues from its "finally" callback).
     */
    public function store(ImportBooksRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('', 'imports');
        $total = BookCsv::countRows(Storage::disk('imports')->path($path));

        $import = Import::create([
            'user_id' => $request->user()->id,
            'warehouse_id' => $request->validated('warehouse_id'),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'status' => ImportStatus::Pending,
            'total_rows' => $total,
        ]);

        $size = config('warehouse.import.chunk_size');
        $chunks = collect(range(0, $total - 1, $size))
            ->map(fn (int $offset) => new ImportBooksChunk($import, $offset, min($size, $total - $offset)));

        // Batch callbacks are serialized closures stored in job_batches: capture plain
        // values ($importId), never $this or models.
        $importId = $import->id;

        Bus::chain([
            new ValidateImportFile($import),
            Bus::batch($chunks->all())
                ->name("Import #{$importId}")
                ->allowFailures()
                ->before(fn (Batch $batch) => Import::whereKey($importId)->update(['batch_id' => $batch->id]))
                // All chunks succeeded (row errors do not fail a chunk).
                ->then(fn (Batch $batch) => Import::settle($importId))
                // First chunk that gave up; its failed() hook has marked the import.
                ->catch(fn (Batch $batch, Throwable $e) => Log::warning("Import #{$importId}: a chunk failed.", ['batch' => $batch->id, 'error' => $e->getMessage()]))
                // Every chunk ran (succeeded or failed for good).
                ->finally(fn (Batch $batch) => Import::settle($importId)),
            new CheckLowStock($import->warehouse_id),
            new SendImportReport($import),
        ])->dispatch();

        return redirect()->route('admin.imports.show', $import)
            ->with('status', __('ui.imports.flash.queued', ['rows' => $total, 'jobs' => $chunks->count()]));
    }

    public function show(Import $import): View
    {
        $import->load(['user:id,name', 'warehouse:id,name']);

        return view('admin.imports.show', [
            'import' => $import,
            'progress' => $this->progressData($import),
        ]);
    }

    /**
     * Polled by the progress page (fetch every 2 s until finished).
     */
    public function progress(Import $import): JsonResponse
    {
        return response()->json($this->progressData($import));
    }

    /**
     * Rejected rows as CSV: row number + reasons.
     */
    public function errors(Import $import): StreamedResponse
    {
        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel then reads UTF-8 correctly
            fputcsv($out, [__('ui.imports.columns.row'), __('ui.imports.columns.errors')]);

            foreach ($import->errors ?? [] as $row => $messages) {
                fputcsv($out, [$row, implode(' ', $messages)]);
            }

            fclose($out);
        }, "import-{$import->id}-errors.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function progressData(Import $import): array
    {
        // job_batches row: job-level progress (how many chunk jobs are done/failed).
        $batch = $import->batch_id ? Bus::findBatch($import->batch_id) : null;
        $processed = $import->processedRows();

        return [
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'finished' => $import->status->isFinished(),
            'total' => $import->total_rows,
            'imported' => $import->imported,
            'failed' => $import->failed,
            'processed' => $processed,
            'percentage' => $import->total_rows > 0 ? (int) floor($processed * 100 / $import->total_rows) : 0,
            'batch' => $batch ? [
                'progress' => $batch->progress(),
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'failed_jobs' => $batch->failedJobs,
            ] : null,
            'errors' => array_slice($import->errors ?? [], 0, 20, true),
        ];
    }
}
