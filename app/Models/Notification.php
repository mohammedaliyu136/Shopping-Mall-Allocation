<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SRS FR-130 to FR-133 - notifications and their delivery targets. */
#[Fillable([
    'title', 'message', 'channel', 'target_type', 'target_ref', 'event',
    'status', 'created_by', 'sent_at',
])]
class Notification extends Model
{
    // FR-132
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const IN_SYSTEM = 'in_system';

    public const CHANNELS = [self::IN_SYSTEM, self::EMAIL, self::SMS];

    // FR-131
    public const TARGET_ALL = 'all';

    public const TARGET_OWNER = 'owner';

    public const TARGET_BLOCK = 'block';

    public const TARGET_OUTSTANDING = 'outstanding';

    public const TARGET_USER = 'user';

    public const TARGET_TYPES = [
        self::TARGET_ALL, self::TARGET_OWNER, self::TARGET_BLOCK,
        self::TARGET_OUTSTANDING, self::TARGET_USER,
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
