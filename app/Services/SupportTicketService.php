<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS 6.15 - support ticket creation, assignment, status handling and the
 * conversation retained against each ticket.
 */
class SupportTicketService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * FR-140 - an owner raises a ticket.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(Owner $owner, array $attributes): SupportTicket
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $ticket = SupportTicket::create([
                'ticket_number' => $this->references->ticketNumber(),
                'owner_id' => $owner->getKey(),
                'shop_id' => $attributes['shop_id'] ?? $owner->activeAllocations()->value('shop_id'),
                'category' => $attributes['category'],
                'subject' => $attributes['subject'],
                'description' => $attributes['description'],
                'priority' => $attributes['priority'] ?? 'normal',
                'status' => SupportTicket::OPEN,
            ]);

            // FR-143 - the opening description starts the ticket conversation.
            TicketMessage::create([
                'support_ticket_id' => $ticket->getKey(),
                'owner_id' => $owner->getKey(),
                'body' => $attributes['description'],
            ]);

            return $ticket->fresh('messages');
        });
    }

    /** FR-143 - add a message to the ticket conversation. */
    public function reply(SupportTicket $ticket, string $body, ?User $user = null, ?Owner $owner = null, bool $internal = false): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $body, $user, $owner, $internal) {
            $message = TicketMessage::create([
                'support_ticket_id' => $ticket->getKey(),
                'user_id' => $user?->getKey(),
                'owner_id' => $owner?->getKey(),
                'body' => $body,
                'is_internal' => $internal,
            ]);

            // A staff reply moves an open ticket forward; an owner reply reopens
            // one that was waiting on them (FR-142).
            if ($user && ! $internal && $ticket->status === SupportTicket::OPEN) {
                $ticket->update(['status' => SupportTicket::IN_PROGRESS]);
            }

            if ($owner && $ticket->status === SupportTicket::AWAITING_OWNER) {
                $ticket->update(['status' => SupportTicket::IN_PROGRESS]);
            }

            // FR-133 - the owner is notified when their ticket receives a response.
            if ($user && ! $internal) {
                $this->notifications->ticketResponded($ticket->fresh());
            }

            return $message;
        });
    }

    public function attach(SupportTicket $ticket, string $path, ?string $originalName = null, ?TicketMessage $message = null): TicketAttachment
    {
        return TicketAttachment::create([
            'support_ticket_id' => $ticket->getKey(),
            'ticket_message_id' => $message?->getKey(),
            'file_path' => $path,
            'original_name' => $originalName,
        ]);
    }

    /** FR-140 - assign a ticket to a support officer or department. */
    public function assign(SupportTicket $ticket, ?User $assignee, ?string $department = null): SupportTicket
    {
        $ticket->update([
            'assigned_to' => $assignee?->getKey(),
            'assigned_department' => $department,
            'status' => $ticket->status === SupportTicket::OPEN ? SupportTicket::IN_PROGRESS : $ticket->status,
        ]);

        return $ticket->fresh('assignee');
    }

    /** FR-142 - move a ticket through its lifecycle. */
    public function changeStatus(SupportTicket $ticket, string $status): SupportTicket
    {
        if (! in_array($status, SupportTicket::STATUSES, true)) {
            throw new \InvalidArgumentException("Unknown ticket status \"{$status}\".");
        }

        $ticket->update([
            'status' => $status,
            'resolved_at' => $status === SupportTicket::RESOLVED ? Carbon::now() : $ticket->resolved_at,
            'closed_at' => $status === SupportTicket::CLOSED ? Carbon::now() : $ticket->closed_at,
        ]);

        return $ticket->fresh();
    }
}
