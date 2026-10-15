<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run on top of the Laravel TestCase so they get the full
| application (HTTP helpers, database, etc.). Unit tests stay framework-free.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * N+1 regression guard: seed 5 and then 30 related records (each inside a
 * rolled-back transaction) and assert the request runs the same number of
 * queries. With an N+1, the count grows with the data.
 *
 * @param  Closure(int $count): mixed  $seed  returns context passed to $request
 * @param  Closure(mixed $context): TestResponse  $request
 */
function expectConstantQueries(Closure $seed, Closure $request): void
{
    $counts = [];
    $logs = [];

    foreach ([5, 30] as $size) {
        DB::beginTransaction();

        try {
            $context = $seed($size);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $request($context)->assertOk();
            $logs[$size] = array_column(DB::getQueryLog(), 'query');
            $counts[$size] = count($logs[$size]);
            DB::disableQueryLog();
        } finally {
            DB::rollBack();
        }
    }

    expect($counts[30])->toBe($counts[5], sprintf(
        "Query count grows with data (%d with 5 records, %d with 30) — likely N+1. Extra queries:\n%s",
        $counts[5],
        $counts[30],
        implode("\n", array_slice(array_diff_assoc($logs[30], $logs[5]), 0, 5)),
    ));
}
