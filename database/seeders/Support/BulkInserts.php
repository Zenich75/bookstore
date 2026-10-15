<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;

/**
 * Why bulk inserts instead of Model::create()/factory()->create() here?
 *
 * Creating 50k books + ~200k reviews one model at a time means one INSERT
 * round-trip per row plus model events, casts and timestamps for each — that
 * takes many minutes. A multi-row INSERT of 1000 rows via the Query Builder is
 * one statement per chunk and is orders of magnitude faster. The trade-off:
 * no model events, casts or automatic timestamps (we fill those by hand) — so
 * StockObserver writes no movements and the "encrypted" cast does not encrypt
 * profiles.phone; the seeders do these explicitly. (DatabaseSeeder also mutes
 * model events entirely with WithoutModelEvents.)
 * Stage 5 (performance) compares both approaches in detail.
 */
trait BulkInserts
{
    protected int $chunkSize = 1000;

    /**
     * Insert rows into a table in chunks of $chunkSize rows per statement.
     *
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function insertChunked(string $table, iterable $rows): int
    {
        $buffer = [];
        $inserted = 0;

        foreach ($rows as $row) {
            $buffer[] = $row;

            if (count($buffer) === $this->chunkSize) {
                DB::table($table)->insert($buffer);
                $inserted += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table($table)->insert($buffer);
            $inserted += count($buffer);
        }

        return $inserted;
    }

    /**
     * Pick $count distinct random values (0 => empty array).
     *
     * @template T
     *
     * @param  array<int, T>  $values
     * @return array<int, T>
     */
    protected function pickRandom(array $values, int $count): array
    {
        $count = min($count, count($values));

        if ($count <= 0) {
            return [];
        }

        $keys = (array) array_rand($values, $count);
        shuffle($keys);

        return array_map(fn ($key) => $values[$key], $keys);
    }
}
