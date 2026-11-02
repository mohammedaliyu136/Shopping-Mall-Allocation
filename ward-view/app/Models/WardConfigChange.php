<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/** Append-only log of every change made on the admin setup screen. */
class WardConfigChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ward_id', 'user_id', 'user_name', 'event', 'before', 'after', 'version'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Field => [old, new] for fields that changed. */
    public function diff(): array
    {
        $before = $this->before ?? [];
        $diff = [];
        foreach ($this->after as $field => $new) {
            $old = $before[$field] ?? null;
            if ($old !== $new) {
                $diff[$field] = [$old, $new];
            }
        }

        return $diff;
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('Ward configuration changes are immutable.');
    }

    public function delete(): bool
    {
        throw new RuntimeException('Ward configuration changes cannot be deleted.');
    }
}
