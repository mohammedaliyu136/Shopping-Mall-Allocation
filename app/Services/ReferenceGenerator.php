<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\AllocationCard;
use App\Models\AllocationDocument;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\OwnershipTransfer;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SRS FR-051 - generates the unique, configurable reference numbers used across
 * the system (allocations, invoices, receipts, cards, tickets and transfers).
 */
class ReferenceGenerator
{
    /**
     * FR-051 - allocation reference, e.g. MALL/2026/BLK-A/000125.
     *
     * The format lives in system settings and supports the placeholders
     * {mall}, {year}, {block} and {sequence}.
     */
    public function allocationReference(Shop $shop, ?Carbon $date = null): string
    {
        $format = SystemSetting::get('allocation.reference_format', '{mall}/{year}/{block}/{sequence}');
        $date ??= Carbon::now();

        $replacements = [
            '{mall}' => $shop->mall?->code ?? 'MALL',
            '{year}' => $date->format('Y'),
            '{block}' => $shop->block?->code ?? 'GEN',
        ];

        return $this->nextFree(
            Allocation::class,
            'reference_number',
            fn (int $n) => strtr($format, $replacements + ['{sequence}' => $this->pad($n)]),
        );
    }

    public function ownerCode(): string
    {
        return $this->sequential(Owner::class, 'owner_code', 'OWN');
    }

    public function invoiceNumber(?Carbon $date = null): string
    {
        return $this->sequential(Invoice::class, 'invoice_number', 'INV-'.$this->year($date));
    }

    public function paymentReference(?Carbon $date = null): string
    {
        return $this->sequential(Payment::class, 'payment_reference', 'PAY-'.$this->year($date));
    }

    public function receiptNumber(?Carbon $date = null): string
    {
        return $this->sequential(Receipt::class, 'receipt_number', 'RCP-'.$this->year($date));
    }

    public function cardNumber(?Carbon $date = null): string
    {
        return $this->sequential(AllocationCard::class, 'card_number', 'CARD-'.$this->year($date));
    }

    public function documentNumber(string $type, ?Carbon $date = null): string
    {
        $prefix = $type === AllocationDocument::CARD ? 'DOC-CARD-' : 'DOC-LTR-';

        return $this->sequential(AllocationDocument::class, 'document_number', $prefix.$this->year($date));
    }

    public function transferReference(?Carbon $date = null): string
    {
        return $this->sequential(OwnershipTransfer::class, 'reference_number', 'TRF-'.$this->year($date));
    }

    public function ticketNumber(?Carbon $date = null): string
    {
        return $this->sequential(SupportTicket::class, 'ticket_number', 'TKT-'.$this->year($date));
    }

    /**
     * SRS 7.2 / FR-080 - a secure, non-guessable identifier for QR verification links.
     */
    public function verificationToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (Allocation::query()->where('verification_token', $token)->exists());

        return $token;
    }

    /**
     * Builds a `PREFIX-000001` style reference.
     *
     * @param  class-string<Model>  $model
     */
    private function sequential(string $model, string $column, string $prefix): string
    {
        return $this->nextFree($model, $column, fn (int $n) => $prefix.'-'.$this->pad($n));
    }

    /**
     * Returns the first value produced by $formatter that is not already taken.
     * The unique index on the column remains the real guard; this picks a
     * starting point and steps past any value already in use.
     *
     * @param  class-string<Model>  $model
     * @param  callable(int): string  $formatter
     */
    private function nextFree(string $model, string $column, callable $formatter): string
    {
        $table = (new $model)->getTable();
        $start = DB::table($table)->count() + 1;

        for ($sequence = $start; $sequence < $start + 1000; $sequence++) {
            $candidate = $formatter($sequence);

            if (! DB::table($table)->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to allocate a unique {$column} for {$model}.");
    }

    private function pad(int $number, int $width = 6): string
    {
        return str_pad((string) $number, $width, '0', STR_PAD_LEFT);
    }

    private function year(?Carbon $date): string
    {
        return ($date ?? Carbon::now())->format('Y');
    }
}
