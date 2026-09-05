<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\NotificationRecipient;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** SRS FR-150 - the owner dashboard. */
class DashboardController extends Controller
{
    public function index(): View
    {
        $owner = Auth::user()->owner;

        return view('portal.dashboard', [
            'owner' => $owner,
            'allocations' => $owner->allocations()
                ->whereIn('status', Allocation::OCCUPYING_STATUSES)
                ->with(['shop.block', 'activeCard'])
                ->get(),
            'outstanding' => $owner->outstandingBalance(),
            'currentBills' => $owner->invoices()
                ->whereIn('status', Invoice::OPEN_STATUSES)
                ->with('shop')
                ->orderBy('due_date')
                ->get(),
            'recentPayments' => $owner->payments()
                ->with(['invoice', 'receipt'])
                ->latest('payment_date')->limit(5)->get(),
            'notifications' => NotificationRecipient::query()
                ->where('owner_id', $owner->id)
                ->with('notification')
                ->latest('id')->limit(5)->get(),
            'tickets' => $owner->tickets()->latest('id')->limit(5)->get(),
            'unreadCount' => NotificationRecipient::query()
                ->where('owner_id', $owner->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }
}
