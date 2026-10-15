<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Review moderation. Controller middleware declared in code (HasMiddleware) —
 * the alternative to the #[Authorize]/#[Middleware] attributes on Admin\BookController.
 * The {review} route parameter is bound without ApprovedScope (routes/web.php),
 * otherwise pending reviews — the whole point of this page — would be 404s.
 */
class ReviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:approve,review', only: ['approve']),
            new Middleware('can:delete,review', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('admin.reviews.index', [
            'reviews' => Review::withoutGlobalScope(ApprovedScope::class)
                ->where('is_approved', false)
                ->with(['user:id,name', 'book' => fn ($query) => $query->withTrashed()->select(['id', 'title', 'slug', 'deleted_at'])])
                ->oldest('id')
                ->paginate(20),
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => true]);

        return back()->with('status', __('ui.admin.flash.review_approved'));
    }

    /**
     * Reject = delete (the author may write a new review then).
     */
    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('status', __('ui.admin.flash.review_rejected'));
    }
}
