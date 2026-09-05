<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS FR-170 / FR-171 - audit trail. Records are append-only: the application
 * exposes no update or delete path for them.
 */
#[Fillable([
    'user_id', 'user_name', 'action', 'auditable_type', 'auditable_id',
    'old_values', 'new_values', 'description', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** FR-171 - audit records are not editable. */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Audit log entries are immutable.');
    }

    public function delete(): bool
    {
        throw new \RuntimeException('Audit log entries cannot be deleted.');
    }
}
