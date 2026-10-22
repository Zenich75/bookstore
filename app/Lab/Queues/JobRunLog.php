<?php

namespace App\Lab\Queues;

use Illuminate\Support\Facades\DB;

/**
 * Worker-side measurements of the /lab/queues demo jobs (lab_job_runs table):
 * the page reads them back, whichever process ran the job.
 */
final class JobRunLog
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function record(string $variant, object $job, array $details = [], ?int $payloadBytes = null, ?int $restoreQueries = null): void
    {
        DB::table('lab_job_runs')->insert([
            'variant' => $variant,
            'job' => class_basename($job),
            'queue' => property_exists($job, 'job') && $job->job ? $job->job->getQueue().' @ '.$job->job->getConnectionName() : 'sync',
            'attempt' => method_exists($job, 'attempts') ? $job->attempts() : 1,
            'payload_bytes' => $payloadBytes,
            'restore_queries' => $restoreQueries,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
