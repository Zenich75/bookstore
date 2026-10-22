<?php

namespace App\Lab\Queues;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Plain ids: nothing is restored up front; handle() decides what to query and how
 * much at a time (here: only the price column, in chunks of 200).
 */
class BookIdsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MeasuresModelRestoration, Queueable;

    /**
     * @param  list<int>  $bookIds
     */
    public function __construct(public array $bookIds) {}

    public function handle(): void
    {
        $queries = 0;
        $sum = 0;

        foreach (array_chunk($this->bookIds, 200) as $ids) {
            $sum += (int) DB::table('books')->whereIn('id', $ids)->sum('price');
            $queries++;
        }

        JobRunLog::record('ids', $this, [
            'ids' => count($this->bookIds),
            'handle_queries' => $queries,
            'price_sum_cents' => $sum,
            'memory_mb' => round(memory_get_peak_usage() / 1048576, 1),
        ], $this->payloadBytes, $this->restoreQueries);
    }
}
