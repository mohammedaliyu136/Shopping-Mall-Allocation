@php
    /* Maps a domain status onto a badge tone. */
    $tone = match ($status) {
        'active', 'approved', 'paid', 'confirmed', 'available', 'resolved', 'closed', 'sent' => 'ok',
        'pending_approval', 'pending', 'reserved', 'partially_paid', 'draft', 'in_progress',
        'awaiting_owner_response', 'suspended', 'occupied' => 'warn',
        'cancelled', 'overdue', 'failed', 'rejected', 'reversed', 'expired' => 'bad',
        default => 'muted',
    };
@endphp
<span class="badge {{ $tone }}">{{ str($status)->replace('_', ' ')->title() }}</span>
