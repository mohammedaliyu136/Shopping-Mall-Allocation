<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Owner;
use App\Models\OwnershipTransfer;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** SRS FR-120 - owners may initiate an ownership transfer request. */
class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function index(): View
    {
        $ownerId = Auth::user()->owner_id;

        return view('portal.transfers.index', [
            'transfers' => OwnershipTransfer::query()
                ->where('from_owner_id', $ownerId)
                ->orWhere('to_owner_id', $ownerId)
                ->with(['shop.block', 'fromOwner', 'toOwner'])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $owner = Auth::user()->owner;

        return view('portal.transfers.form', [
            'allocations' => $owner->allocations()
                ->whereIn('status', [Allocation::ACTIVE, Allocation::SUSPENDED])
                ->with('shop.block')
                ->get(),
            'outstanding' => $owner->outstandingBalance(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $owner = Auth::user()->owner;

        $validated = $request->validate([
            'allocation_id' => ['required', 'exists:allocations,id'],
            'to_owner_email' => ['required', 'email', 'exists:owners,email'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $allocation = Allocation::findOrFail($validated['allocation_id']);

        // An owner may only transfer an allocation they themselves hold.
        abort_if($allocation->owner_id !== $owner->id, 403);

        $newOwner = Owner::query()->where('email', $validated['to_owner_email'])->firstOrFail();

        try {
            $transfer = $this->transfers->request($allocation, $newOwner, $validated['reason']);
        } catch (TransferException $e) {
            return back()->withInput()->withErrors(['allocation_id' => $e->getMessage()]);
        }

        return redirect()->route('portal.transfers.index')->with(
            'status',
            'Transfer request '.$transfer->reference_number.' submitted for review by mall management.',
        );
    }
}
