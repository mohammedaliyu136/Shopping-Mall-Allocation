<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** SRS FR-150 - notifications addressed to the signed-in owner. */
class NotificationController extends Controller
{
    public function index(): View
    {
        return view('portal.notifications.index', [
            'recipients' => NotificationRecipient::query()
                ->where('owner_id', Auth::user()->owner_id)
                ->with('notification')
                ->latest('id')
                ->paginate(25),
        ]);
    }

    public function markRead(NotificationRecipient $recipient): RedirectResponse
    {
        abort_if($recipient->owner_id !== Auth::user()->owner_id, 403);

        $recipient->update([
            'status' => NotificationRecipient::READ,
            'read_at' => $recipient->read_at ?? Carbon::now(),
        ]);

        return back();
    }
}
