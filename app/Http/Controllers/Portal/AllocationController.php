<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** SRS FR-150 / FR-151 - the owner's allocations and their documents. */
class AllocationController extends Controller
{
    public function __construct(private readonly QrCodeService $qr) {}

    public function index(): View
    {
        $owner = Auth::user()->owner;

        return view('portal.allocations.index', [
            // Includes closed and transferred allocations: an owner keeps sight
            // of their own history (FR-123).
            'allocations' => $owner->allocations()
                ->with(['shop.block', 'mall', 'activeCard'])
                ->latest('allocation_date')
                ->get(),
        ]);
    }

    public function show(Allocation $allocation): View
    {
        $this->authorize('view-allocation', $allocation);

        $allocation->load(['shop.block', 'shop.floor', 'mall', 'documents', 'cards', 'invoices']);

        return view('portal.allocations.show', [
            'allocation' => $allocation,
            'qr' => $this->qr->svg($this->qr->verificationUrl($allocation), 150),
            'verificationUrl' => $this->qr->verificationUrl($allocation),
        ]);
    }
}
