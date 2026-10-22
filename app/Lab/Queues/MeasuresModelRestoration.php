<?php

namespace App\Lab\Queues;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * SerializesModels with a stopwatch: counts the queries that re-fetch the job's
 * models (and their eager-loaded relations) when the worker unserializes the
 * payload — before handle() runs, so job middleware could not see them either.
 */
trait MeasuresModelRestoration
{
    use SerializesModels {
        __unserialize as restoreModels;
    }

    public ?int $restoreQueries = null;

    /**
     * Size of the serialized job (the "command" part of the queue payload), set
     * by the lab before dispatch.
     */
    public ?int $payloadBytes = null;

    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void
    {
        $connection = DB::connection();
        $wasLogging = $connection->logging();
        $connection->enableQueryLog();
        $before = count($connection->getQueryLog());

        $this->restoreModels($values);

        $this->restoreQueries = count($connection->getQueryLog()) - $before;

        if (! $wasLogging) {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }
}
