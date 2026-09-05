<?php

namespace App\Http\Controllers\Admin;

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

/** SRS 6.13 - ownership transfer requests, obligation checks and approvals. */
class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function index(Request $request): View
    {
        $transfers = OwnershipTransfer::query()
            ->with(['fromOwner', 'toOwner', 'shop.block', 'allocation'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.transfers.index', ['transfers' => $transfers]);
    }

    public function create(Request $request): View
    {
        return view('admin.transfers.form', [
            'allocations' => Allocation::query()
                ->whereIn('status', [Allocation::ACTIVE, Allocation::SUSPENDED])
                ->with(['owner', 'shop.block'])
                ->get(),
            'owners' => Owner::query()->where('status', 'active')->orderBy('full_name')->get(),
            'selectedAllocation' => $request->integer('allocation_id') ?: null,
        ]);
    }

    /** FR-120 - raise a transfer request. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'allocation_id' => ['required', 'exists:allocations,id'],
            'to_owner_id' => ['required', 'exists:owners,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $allocation = Allocation::findOrFail($validated['allocation_id']);
        $newOwner = Owner::findOrFail($validated['to_owner_id']);

        try {
            $transfer = $this->transfers->request($allocation, $newOwner, $validated['reason'] ?? null);
        } catch (TransferException $e) {
            return back()->withInput()->withErrors(['allocation_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.transfers.show', $transfer)
            ->with('status', 'Transfer request '.$transfer->reference_number.' created.');
    }

    public function show(OwnershipTransfer $transfer): View
    {
        $transfer->load([
            'fromOwner', 'toOwner', 'shop.block', 'allocation', 'newAllocation',
            'documents.uploader', 'requester', 'approver',
        ]);

        return view('admin.transfers.show', [
            'transfer' => $transfer,
            // FR-121 - obligations are re-checked at approval time.
            'outstanding' => $this->transfers->outstandingObligations($transfer),
        ]);
    }

    /** FR-120 - attach supporting documents. */
    public function storeDocument(Request $request, OwnershipTransfer $transfer): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $file = $request->file('file');

        $transfer->documents()->create([
            'title' => $validated['title'],
            'file_path' => $file->store('transfers/documents'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('status', 'Supporting document uploaded.');
    }

    /** FR-121 / FR-122 / FR-124 - approve, closing the old allocation and issuing a new one. */
    public function approve(OwnershipTransfer $transfer): RedirectResponse
    {
        try {
            $transfer = $this->transfers->approve($transfer, Auth::user());
        } catch (TransferException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()->route('admin.transfers.show', $transfer)->with(
            'status',
            'Transfer approved. New allocation '.$transfer->newAllocation?->reference_number.' issued with a fresh letter, card and QR code.',
        );
    }

    public function reject(Request $request, OwnershipTransfer $transfer): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->transfers->reject($transfer, Auth::user(), $validated['reason']);
        } catch (TransferException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()->route('admin.transfers.show', $transfer)->with('status', 'Transfer request rejected.');
    }
}
