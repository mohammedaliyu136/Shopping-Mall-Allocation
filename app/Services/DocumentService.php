<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\AllocationCard;
use App\Models\AllocationDocument;
use App\Models\Invoice;
use App\Models\Receipt;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * SRS FR-061, FR-070, FR-091, FR-110 - renders allocation letters, allocation
 * cards, invoices and receipts as printable PDF documents.
 */
class DocumentService
{
    public function __construct(private readonly QrCodeService $qr) {}

    /** FR-060 / FR-061 - the allocation letter. */
    public function allocationLetter(Allocation $allocation): string
    {
        $allocation->loadMissing(['owner', 'shop.block', 'shop.floor', 'mall', 'letter']);

        return $this->render('documents.allocation-letter', [
            'allocation' => $allocation,
            'document' => $allocation->letter,
            'qr' => $this->qr->forAllocation($allocation, 160),
            'verificationUrl' => $this->qr->verificationUrl($allocation),
        ]);
    }

    /** FR-070 - the allocation card. */
    public function allocationCard(AllocationCard $card): string
    {
        $card->loadMissing(['allocation.owner', 'allocation.shop.block', 'allocation.mall']);

        // ID-1 card (85.6 x 54 mm) expressed as a Dompdf bounding box in points.
        return $this->render('documents.allocation-card', [
            'card' => $card,
            'allocation' => $card->allocation,
            'qr' => $this->qr->forAllocation($card->allocation, 130),
        ], 'portrait', [0, 0, 242.6, 153.0]);
    }

    /** FR-091 - the invoice. */
    public function invoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['owner', 'shop.block', 'items.billingCategory', 'allocation.mall', 'confirmedPayments']);

        return $this->render('documents.invoice', ['invoice' => $invoice]);
    }

    /** FR-110 - the payment receipt. */
    public function receipt(Receipt $receipt): string
    {
        $receipt->loadMissing(['payment.invoice.shop.block', 'payment.owner', 'payment.invoice.allocation.mall']);

        return $this->render('documents.receipt', ['receipt' => $receipt]);
    }

    public function filenameFor(string $type, string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $reference);

        return trim("{$type}-{$safe}", '-').'.pdf';
    }

    /**
     * Records the stored path for a generated document so the audit trail and
     * the owner portal can point at the same artefact (FR-062).
     */
    public function markIssued(AllocationDocument $document, string $path): AllocationDocument
    {
        $document->update(['file_path' => $path]);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{0: float, 1: float, 2: float, 3: float}|string  $paper
     */
    private function render(string $view, array $data, string $orientation = 'portrait', array|string $paper = 'A4'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);   // 7.2 - no outbound fetches while rendering
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(public_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(View::make($view, $data)->render());
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
