<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Owner;
use App\Models\OwnershipTransfer;
use App\Models\Payment;
use App\Models\SupportTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SRS 6.14 - notification creation, targeting, channels and the automated
 * notifications listed in FR-133.
 *
 * In-system notifications are delivered by writing recipient rows, which the
 * owner portal reads. Email uses the configured mailer; SMS is dispatched
 * through a gateway driver, which ships as a log driver until a provider is
 * configured (SRS 11 - Additional Services).
 */
class NotificationService
{
    /**
     * FR-130 / FR-131 - create a notification and resolve its recipients.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $title, string $message, array $attributes = []): Notification
    {
        return DB::transaction(function () use ($title, $message, $attributes) {
            $notification = Notification::create([
                'title' => $title,
                'message' => $message,
                'channel' => $attributes['channel'] ?? Notification::IN_SYSTEM,
                'target_type' => $attributes['target_type'] ?? Notification::TARGET_ALL,
                'target_ref' => $attributes['target_ref'] ?? null,
                'event' => $attributes['event'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            foreach ($this->resolveRecipients($notification) as $owner) {
                NotificationRecipient::create([
                    'notification_id' => $notification->getKey(),
                    'owner_id' => $owner->getKey(),
                    'channel' => $notification->channel,
                    'destination' => $this->destinationFor($owner, $notification->channel),
                    'status' => NotificationRecipient::PENDING,
                ]);
            }

            return $notification->fresh('recipients');
        });
    }

    /** FR-132 - deliver a notification over its channel and record the outcome. */
    public function send(Notification $notification): Notification
    {
        $notification->recipients()->with('owner')->each(function (NotificationRecipient $recipient) use ($notification) {
            try {
                $this->deliver($notification, $recipient);

                $recipient->update([
                    'status' => NotificationRecipient::SENT,
                    'sent_at' => Carbon::now(),
                    'error' => null,
                ]);
            } catch (\Throwable $e) {
                $recipient->update([
                    'status' => NotificationRecipient::FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 250),
                ]);
            }
        });

        $failed = $notification->recipients()->where('status', NotificationRecipient::FAILED)->count();
        $total = $notification->recipients()->count();

        $notification->update([
            'status' => ($total > 0 && $failed === $total) ? 'failed' : 'sent',
            'sent_at' => Carbon::now(),
        ]);

        return $notification->fresh('recipients');
    }

    /** Convenience: create and immediately dispatch. */
    public function createAndSend(string $title, string $message, array $attributes = []): Notification
    {
        return $this->send($this->create($title, $message, $attributes));
    }

    // ---------------------------------------------------------------------
    // FR-133 - automated notifications
    // ---------------------------------------------------------------------

    public function invoiceGenerated(Invoice $invoice): Notification
    {
        return $this->notifyOwner(
            $invoice->owner_id,
            'Invoice '.$invoice->invoice_number.' has been issued',
            sprintf(
                'An invoice of %s for %s has been issued for the period %s to %s. Payment is due by %s.',
                number_format((float) $invoice->total_amount, 2),
                $invoice->shop?->label() ?? 'your shop',
                $invoice->billing_period_start->toFormattedDateString(),
                $invoice->billing_period_end->toFormattedDateString(),
                $invoice->due_date->toFormattedDateString(),
            ),
            'invoice.generated',
        );
    }

    public function paymentDue(Invoice $invoice): Notification
    {
        return $this->notifyOwner(
            $invoice->owner_id,
            'Payment due for invoice '.$invoice->invoice_number,
            sprintf(
                'A balance of %s on invoice %s falls due on %s.',
                number_format((float) $invoice->balance, 2),
                $invoice->invoice_number,
                $invoice->due_date->toFormattedDateString(),
            ),
            'payment.due',
        );
    }

    public function paymentOverdue(Invoice $invoice): Notification
    {
        return $this->notifyOwner(
            $invoice->owner_id,
            'Overdue invoice '.$invoice->invoice_number,
            sprintf(
                'Invoice %s became overdue on %s with an outstanding balance of %s. Please settle it to avoid penalties.',
                $invoice->invoice_number,
                $invoice->due_date->toFormattedDateString(),
                number_format((float) $invoice->balance, 2),
            ),
            'payment.overdue',
        );
    }

    public function paymentReceived(Payment $payment): Notification
    {
        return $this->notifyOwner(
            $payment->owner_id,
            'Payment received - '.$payment->payment_reference,
            sprintf(
                'We have received your payment of %s on %s. Receipt %s is available in your portal.',
                number_format((float) $payment->amount, 2),
                $payment->payment_date->toFormattedDateString(),
                $payment->receipt?->receipt_number ?? 'pending',
            ),
            'payment.received',
        );
    }

    public function allocationActivated(Allocation $allocation): Notification
    {
        return $this->notifyOwner(
            $allocation->owner_id,
            'Allocation '.$allocation->reference_number.' is now active',
            sprintf(
                'Your allocation of %s is active. Your allocation letter and card are available in your portal.',
                $allocation->shop?->label() ?? 'the shop',
            ),
            'allocation.activated',
        );
    }

    public function allocationStatusChanged(Allocation $allocation): Notification
    {
        return $this->notifyOwner(
            $allocation->owner_id,
            'Allocation '.$allocation->reference_number.' status updated',
            sprintf('The status of your allocation is now "%s".', $allocation->status),
            'allocation.status_changed',
        );
    }

    public function transferUpdated(OwnershipTransfer $transfer): Notification
    {
        return $this->notifyOwner(
            $transfer->from_owner_id,
            'Transfer request '.$transfer->reference_number.' updated',
            sprintf('Your ownership transfer request is now "%s".', $transfer->status),
            'transfer.updated',
        );
    }

    public function ticketResponded(SupportTicket $ticket): Notification
    {
        return $this->notifyOwner(
            $ticket->owner_id,
            'Response on ticket '.$ticket->ticket_number,
            sprintf('Your support ticket "%s" has received a response.', $ticket->subject),
            'ticket.responded',
        );
    }

    // ---------------------------------------------------------------------

    private function notifyOwner(int $ownerId, string $title, string $message, string $event): Notification
    {
        return $this->createAndSend($title, $message, [
            'channel' => Notification::IN_SYSTEM,
            'target_type' => Notification::TARGET_OWNER,
            'target_ref' => (string) $ownerId,
            'event' => $event,
        ]);
    }

    /**
     * FR-131 - resolve the owners a notification is addressed to.
     *
     * @return Collection<int, Owner>
     */
    private function resolveRecipients(Notification $notification)
    {
        $query = Owner::query()->where('status', 'active');

        return match ($notification->target_type) {
            Notification::TARGET_OWNER, Notification::TARGET_USER => Owner::query()
                ->whereKey(explode(',', (string) $notification->target_ref))
                ->get(),

            Notification::TARGET_BLOCK => $query
                ->whereHas('allocations', fn ($a) => $a
                    ->whereIn('status', Allocation::OCCUPYING_STATUSES)
                    ->whereHas('shop', fn ($s) => $s->where('block_id', $notification->target_ref)))
                ->get(),

            // FR-131 - owners carrying an unsettled balance.
            Notification::TARGET_OUTSTANDING => $query
                ->whereHas('invoices', fn ($i) => $i
                    ->whereIn('status', Invoice::OPEN_STATUSES)
                    ->where('balance', '>', 0))
                ->get(),

            default => $query->get(),
        };
    }

    private function destinationFor(Owner $owner, string $channel): ?string
    {
        return match ($channel) {
            Notification::EMAIL => $owner->email,
            Notification::SMS => $owner->phone,
            default => null,
        };
    }

    /** FR-132 - channel dispatch. */
    private function deliver(Notification $notification, NotificationRecipient $recipient): void
    {
        match ($recipient->channel) {
            Notification::EMAIL => $this->deliverEmail($notification, $recipient),
            Notification::SMS => $this->deliverSms($notification, $recipient),
            // In-system notifications are delivered by the recipient row itself.
            default => null,
        };
    }

    private function deliverEmail(Notification $notification, NotificationRecipient $recipient): void
    {
        if (! $recipient->destination) {
            throw new \RuntimeException('No email address on record for this owner.');
        }

        Mail::raw($notification->message, function ($mail) use ($notification, $recipient) {
            $mail->to($recipient->destination)->subject($notification->title);
        });
    }

    private function deliverSms(Notification $notification, NotificationRecipient $recipient): void
    {
        if (! $recipient->destination) {
            throw new \RuntimeException('No phone number on record for this owner.');
        }

        // SRS 11 - an SMS gateway is an external integration. Until one is
        // configured the message is written to the application log so that the
        // delivery record and its outcome are still captured.
        Log::channel(config('logging.default'))->info('SMS notification', [
            'to' => $recipient->destination,
            'title' => $notification->title,
            'message' => $notification->message,
        ]);
    }
}
