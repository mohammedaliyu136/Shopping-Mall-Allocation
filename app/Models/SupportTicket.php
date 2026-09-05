<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-140 to FR-143 - support tickets. */
#[Fillable([
    'ticket_number', 'owner_id', 'shop_id', 'category', 'subject', 'description',
    'priority', 'status', 'assigned_to', 'assigned_department', 'resolved_at', 'closed_at',
])]
class SupportTicket extends Model
{
    use Auditable;

    // FR-142
    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const AWAITING_OWNER = 'awaiting_owner_response';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    public const STATUSES = [
        self::OPEN, self::IN_PROGRESS, self::AWAITING_OWNER, self::RESOLVED, self::CLOSED,
    ];

    // FR-141
    public const CATEGORIES = [
        'electricity', 'water', 'security', 'maintenance', 'billing', 'allocation', 'general_enquiry',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
