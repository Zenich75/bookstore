<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Database-channel notifications of the logged-in staff member (low stock,
 * pending reviews, import reports). The data column holds a translation "kind"
 * and its parameters, so the text follows the current locale.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            // notifications(): morphMany on Notifiable, newest first. No relations to load —
            // everything the view shows is in the data column (constant queries).
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /**
     * Mark one as read and follow its link.
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        // Scoped to the user's own notifications: someone else's id is a 404 (IDOR).
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        // One UPDATE query, no models loaded.
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', __('ui.notifications.flash.all_read'));
    }
}
