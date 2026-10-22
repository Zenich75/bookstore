<?php

namespace App\Listeners;

use App\Events\ReviewApproved;
use App\Events\ReviewDeleted;
use App\Events\ReviewRejected;
use App\Jobs\RecalculateBookRating;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Auto-discovered (app/Listeners): the union type in handle() registers it for all
 * three events. Queued (ShouldQueue) — queue from Queue::route() in AppServiceProvider.
 *
 * ReviewCreated is not listened to: a pending review does not count in the rating.
 */
class DispatchRatingRecalculation implements ShouldQueue
{
    public function handle(ReviewApproved|ReviewRejected|ReviewDeleted $event): void
    {
        RecalculateBookRating::dispatch($event->bookId());
    }

    /**
     * false => the listener is not queued and does not run at all. Removing a pending
     * review cannot change the rating:
     * only approved reviews are averaged.
     */
    public function shouldQueue(ReviewApproved|ReviewRejected|ReviewDeleted $event): bool
    {
        return $event instanceof ReviewApproved || $event->wasApproved;
    }
}
