<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\ReviewCreated;
use App\Models\User;
use App\Notifications\PendingReviewNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyModeratorsOfPendingReview implements ShouldQueue
{
    public function handle(ReviewCreated $event): void
    {
        $review = $event->review->load(['book:id,title,slug', 'user:id,name']);

        Notification::send(
            User::where('role', UserRole::Admin)->get(),
            new PendingReviewNotification($review),
        );
    }
}
