<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SRS 3.3 and 6.9 - the public verification portal.
 *
 * No login is required. Allocations are looked up by their non-guessable
 * verification token (FR-080) or, as a fallback for someone reading a printed
 * document, by allocation reference number.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly QrCodeService $qr) {}

    public function index(): View
    {
        return view('verify.index');
    }

    /** Look up a document by the reference printed on it. */
    public function lookup(Request $request): RedirectResponse|View
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $reference = trim($validated['reference']);

        $allocation = Allocation::query()
            ->where('reference_number', $reference)
            ->orWhereHas('documents', fn ($q) => $q->where('document_number', $reference))
            ->orWhereHas('cards', fn ($q) => $q->where('card_number', $reference))
            ->first();

        if (! $allocation) {
            // FR-082
            return view('verify.result', ['allocation' => null, 'reference' => $reference]);
        }

        return redirect()->route('verify.show', ['token' => $allocation->verification_token]);
    }

    /**
     * FR-081 / FR-082 / FR-083 - the verification result. A cancelled or
     * transferred allocation is shown with its current status rather than
     * being hidden.
     */
    public function show(string $token): View
    {
        $allocation = Allocation::query()
            ->with(['owner', 'shop.block', 'mall', 'activeCard'])
            ->where('verification_token', $token)
            ->first();

        return view('verify.result', [
            'allocation' => $allocation,
            'reference' => null,
            'qr' => $allocation ? $this->qr->svg($this->qr->verificationUrl($allocation), 150) : null,
        ]);
    }
}
