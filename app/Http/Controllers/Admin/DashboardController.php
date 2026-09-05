<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\OwnershipTransfer;
use App\Models\SupportTicket;
use App\Services\ReportService;
use Illuminate\View\View;

/** SRS 3.1 - the administration portal landing page. */
class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->reports->dashboard(),
            'pendingAllocations' => Allocation::query()
                ->where('status', Allocation::PENDING_APPROVAL)
                ->with(['owner', 'shop.block'])
                ->latest()->limit(5)->get(),
            'pendingTransfers' => OwnershipTransfer::query()
                ->where('status', OwnershipTransfer::PENDING)
                ->with(['fromOwner', 'toOwner', 'shop'])
                ->latest()->limit(5)->get(),
            'overdueInvoices' => Invoice::query()->overdue()
                ->with(['owner', 'shop'])
                ->orderBy('due_date')->limit(5)->get(),
            'openTickets' => SupportTicket::query()
                ->whereIn('status', [SupportTicket::OPEN, SupportTicket::IN_PROGRESS])
                ->with('owner')
                ->latest()->limit(5)->get(),
        ]);
    }
}
