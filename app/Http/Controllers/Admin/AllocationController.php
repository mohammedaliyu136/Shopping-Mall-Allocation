<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AllocationException;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Owner;
use App\Models\Shop;
use App\Services\AllocationService;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS 6.6 to 6.9 - allocation management, documents and QR verification. */
class AllocationController extends Controller
{
    public function __construct(
        private readonly AllocationService $allocations,
        private readonly QrCodeService $qr,
    ) {}

    public function index(Request $request): View
    {
        $allocations = Allocation::query()
            ->with(['owner', 'shop.block', 'mall'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('reference_number', 'like', "%{$term}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('full_name', 'like', "%{$term}%"))
                    ->orWhereHas('shop', fn ($s) => $s->where('shop_number', 'like', "%{$term}%"))
            ))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.allocations.index', [
            'allocations' => $allocations,
            'statuses' => Allocation::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        // FR-032 - only shops without a live allocation may be offered.
        $shops = Shop::query()
            ->whereNotIn('status', [Shop::SUSPENDED, Shop::CLOSED])
            ->whereDoesntHave('allocations', fn ($q) => $q->whereIn('status', Allocation::OCCUPYING_STATUSES))
            ->with('block')
            ->orderBy('shop_number')
            ->get();

        return view('admin.allocations.form', [
            'shops' => $shops,
            'owners' => Owner::query()->where('status', 'active')->orderBy('full_name')->get(),
            'selectedShop' => $request->integer('shop_id') ?: null,
        ]);
    }

    /** FR-050 - allocate a shop to an owner. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_id' => ['required', 'exists:shops,id'],
            'owner_id' => ['required', 'exists:owners,id'],
            'allocation_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:allocation_date'],
            'allocation_type' => ['required', Rule::in(['lease', 'outright_purchase', 'temporary', 'concession'])],
            'annual_rent' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'other_fees' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        $shop = Shop::findOrFail($validated['shop_id']);
        $owner = Owner::findOrFail($validated['owner_id']);

        try {
            $allocation = $this->allocations->create($shop, $owner, $validated);
        } catch (AllocationException $e) {
            // FR-032 - duplicate allocation is refused and explained.
            return back()->withInput()->withErrors(['shop_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.allocations.show', $allocation)
            ->with('status', 'Allocation '.$allocation->reference_number.' created as a draft.');
    }

    public function show(Allocation $allocation): View
    {
        $allocation->load([
            'owner', 'shop.block', 'shop.floor', 'mall', 'approvals.approver',
            'documents', 'cards.issuer', 'invoices', 'creator', 'approver',
            'previousAllocation.owner',
        ]);

        return view('admin.allocations.show', [
            'allocation' => $allocation,
            'qr' => $this->qr->svg($this->qr->verificationUrl($allocation), 150),
            'verificationUrl' => $this->qr->verificationUrl($allocation),
        ]);
    }

    /** FR-053 - submit a draft into the approval workflow. */
    public function submit(Allocation $allocation): RedirectResponse
    {
        return $this->run(
            fn () => $this->allocations->submitForApproval($allocation),
            $allocation,
            'Allocation submitted for approval.',
        );
    }

    /** FR-053 - record one approval decision. */
    public function approve(Request $request, Allocation $allocation): RedirectResponse
    {
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        return $this->run(
            fn () => $this->allocations->approve($allocation, Auth::user(), $validated['comment'] ?? null),
            $allocation,
            'Approval recorded.',
        );
    }

    public function reject(Request $request, Allocation $allocation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(
            fn () => $this->allocations->reject($allocation, Auth::user(), $validated['reason']),
            $allocation,
            'Allocation rejected and cancelled.',
        );
    }

    /** FR-052 - an approved allocation becomes active. */
    public function activate(Allocation $allocation): RedirectResponse
    {
        return $this->run(
            fn () => $this->allocations->activate($allocation),
            $allocation,
            'Allocation is now active.',
        );
    }

    public function suspend(Request $request, Allocation $allocation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(
            fn () => $this->allocations->suspend($allocation, $validated['reason']),
            $allocation,
            'Allocation suspended.',
        );
    }

    public function reinstate(Allocation $allocation): RedirectResponse
    {
        return $this->run(
            fn () => $this->allocations->reinstate($allocation),
            $allocation,
            'Allocation reinstated.',
        );
    }

    /** FR-052 / FR-123 - cancelling frees the shop but keeps the record. */
    public function cancel(Request $request, Allocation $allocation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->run(
            fn () => $this->allocations->cancel($allocation, $validated['reason']),
            $allocation,
            'Allocation cancelled. The record is retained in the shop history.',
        );
    }

    /** FR-072 - reissue an allocation card, retaining the previous one. */
    public function reissueCard(Request $request, Allocation $allocation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->run(
            fn () => $this->allocations->reissueCard($allocation, $validated['reason'], Auth::user()),
            $allocation,
            'A replacement allocation card has been issued.',
        );
    }

    /** Runs a lifecycle action, turning a domain failure into a form error. */
    private function run(callable $action, Allocation $allocation, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (AllocationException $e) {
            return back()->withErrors(['allocation' => $e->getMessage()]);
        }

        return redirect()->route('admin.allocations.show', $allocation)->with('status', $message);
    }
}
