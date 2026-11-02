<?php

namespace App\Models;

use App\Enums\EntryCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single signed record on the patient's file. Saving an entry is the
 * author's signature; co-signing is inline on the entry.
 */
class ClinicalEntry extends Model
{
    protected $fillable = [
        'encounter_id', 'user_id', 'parent_id', 'category', 'action_key', 'type', 'summary',
        'payload', 'status', 'score', 'recorded_at', 'cosigned_by', 'cosigned_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => EntryCategory::class,
            'payload' => 'array',
            'recorded_at' => 'datetime',
            'cosigned_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cosigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cosigned_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function data(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    public function canBeCosignedBy(User $user): bool
    {
        return ! $this->cosigned_at && $user->id !== $this->user_id && $user->isClinical();
    }
}
