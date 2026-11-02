<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    protected $fillable = [
        'encounter_id', 'kind', 'score', 'risk_level', 'notes', 'user_id', 'completed_at', 'next_due_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'next_due_at' => 'datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
