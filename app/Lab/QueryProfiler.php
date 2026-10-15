<?php

namespace App\Lab;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Measures a piece of code: query count, DB time, wall time, peak memory,
 * and which queries repeat (grouped by SQL with bindings/ids stripped).
 */
final class QueryProfiler
{
    /**
     * Run $callback $runs times and report the last run, so the first run
     * warms up autoloading, compiled views and DB caches.
     */
    public static function run(Closure $callback, int $runs = 2): Profile
    {
        $profile = null;

        for ($i = 0; $i < max(1, $runs); $i++) {
            $profile = self::measure($callback);

            if ($profile->error !== null) {
                break;
            }
        }

        return $profile;
    }

    private static function measure(Closure $callback): Profile
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        $start = hrtime(true);

        $result = null;
        $error = null;

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $error = class_basename($e).': '.$e->getMessage();
        }

        $wallMs = (hrtime(true) - $start) / 1e6;
        $memory = max(0, memory_get_peak_usage() - $baseline);
        $log = $connection->getQueryLog();

        $connection->disableQueryLog();
        $connection->flushQueryLog();

        return new Profile(
            queries: count($log),
            dbMs: round(array_sum(array_column($log, 'time')), 2),
            wallMs: round($wallMs, 2),
            memoryBytes: $memory,
            groups: self::group($log),
            result: $result,
            error: $error,
        );
    }

    /**
     * @param  list<array{query: string, bindings: array<mixed>, time: float|null}>  $log
     * @return list<array{sql: string, count: int, ms: float}>
     */
    private static function group(array $log): array
    {
        $groups = [];

        foreach ($log as $entry) {
            $sql = self::normalize($entry['query']);
            $groups[$sql] ??= ['sql' => $sql, 'count' => 0, 'ms' => 0.0];
            $groups[$sql]['count']++;
            $groups[$sql]['ms'] += (float) $entry['time'];
        }

        usort($groups, fn (array $a, array $b) => [$b['count'], $b['ms']] <=> [$a['count'], $a['ms']]);

        return array_map(fn (array $group) => [...$group, 'ms' => round($group['ms'], 2)], $groups);
    }

    /**
     * "... where id in (1, 2, 3) and type = 'book' limit 1" => "... where id in (…) and type = ? limit ?"
     * Eager loads inline integer keys into the SQL, so literals must go too.
     */
    public static function normalize(string $sql): string
    {
        // Possessive quantifiers (++): eager loads can inline 50k ids, and a backtracking
        // pattern would hit PCRE's backtrack limit (preg_replace() then returns null).
        $sql = preg_replace('/\bin\s*\((?:[\s,?\d]|\'[^\']*+\')++\)/i', 'in (…)', $sql) ?? $sql;
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*+'/", '?', $sql) ?? $sql;
        $sql = preg_replace('/(?<![\w`".])\d+(?:\.\d+)?(?![\w`"])/', '?', $sql) ?? $sql;

        return preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
    }
}
