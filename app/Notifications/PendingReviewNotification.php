<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * "A review awaits moderation" — to every admin.
 */
class PendingReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $bookTitle;

    public string $reviewer;

    public int $rating;

    public string $excerpt;

    /**
     * A snapshot of the review instead of the model: the author may edit or delete
     * it before the queued notification is sent.
     */
    public function __construct(Review $review)
    {
        $this->bookTitle = $review->book->title;
        $this->reviewer = $review->user->name;
        $this->rating = $review->rating;
        $this->excerpt = Str::limit($review->body, 200);
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.pending_review.subject', ['title' => $this->bookTitle]))
            ->markdown('mail.pending-review', [
                'title' => $this->bookTitle,
                'reviewer' => $this->reviewer,
                'rating' => $this->rating,
                'excerpt' => $this->excerpt,
                'url' => route('admin.reviews.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'pending_review',
            'params' => ['title' => $this->bookTitle, 'reviewer' => $this->reviewer],
            'url' => route('admin.reviews.index'),
        ];
    }
}
