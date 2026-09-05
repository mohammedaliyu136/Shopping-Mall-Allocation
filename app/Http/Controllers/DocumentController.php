<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\AllocationCard;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS FR-061, FR-070, FR-091, FR-110 - PDF downloads.
 *
 * Staff reach these through the administration portal and owners through their
 * own portal; each action authorises the caller against the record requested
 * (FR-011, FR-151).
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    /** FR-061 - the allocation letter, downloadable and printable. */
    public function allocationLetter(Request $request, Allocation $allocation): Response
    {
        $this->authorize('view-allocation', $allocation);

        return $this->stream(
            $this->documents->allocationLetter($allocation),
            $this->documents->filenameFor('allocation-letter', $allocation->reference_number),
            $request,
        );
    }

    /** FR-070 - the allocation card. */
    public function allocationCard(Request $request, Allocation $allocation): Response
    {
        $this->authorize('view-allocation', $allocation);

        $card = $allocation->cards()->where('status', AllocationCard::ACTIVE)->first()
            ?? $allocation->cards()->first();

        abort_if(! $card, 404, 'No allocation card has been issued for this allocation.');

        return $this->stream(
            $this->documents->allocationCard($card),
            $this->documents->filenameFor('allocation-card', $card->card_number),
            $request,
        );
    }

    /** FR-091 - the invoice. */
    public function invoice(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view-invoice', $invoice);

        return $this->stream(
            $this->documents->invoice($invoice),
            $this->documents->filenameFor('invoice', $invoice->invoice_number),
            $request,
        );
    }

    /** FR-111 - owners can download and print their receipts. */
    public function receipt(Request $request, Receipt $receipt): Response
    {
        $this->authorize('view-payment', $receipt->payment);

        return $this->stream(
            $this->documents->receipt($receipt),
            $this->documents->filenameFor('receipt', $receipt->receipt_number),
            $request,
        );
    }

    /** `?inline=1` previews in the browser; the default downloads the file. */
    private function stream(string $pdf, string $filename, Request $request): Response
    {
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
        ]);
    }
}
