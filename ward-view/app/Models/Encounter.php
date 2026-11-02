<?php

namespace App\Models;

use App\Enums\EncounterStatus;
use App\Enums\EntryCategory;
use App\Enums\TriageCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One stay: an A&E attendance or a ward admission. Clinical entries, transfers
 * and risk assessments hang off it.
 */
class Encounter extends Model
{
    protected $fillable = [
        'patient_id', 'ward_id', 'bed_id', 'team_id', 'status', 'presenting_complaint', 'diagnoses',
        'arrived_at', 'admitted_at', 'triage_category', 'triaged_at', 'seen_at', 'disposition',
        'assigned_nurse_id', 'off_ward', 'discharge_type', 'discharge_checklist', 'discharge_started_at',
        'discharged_at', 'obs_frequency_hours',
    ];

    protected function casts(): array
    {
        return [
            'status' => EncounterStatus::class,
            'triage_category' => TriageCategory::class,
            'diagnoses' => 'array',
            'discharge_checklist' => 'array',
            'arrived_at' => 'datetime',
            'admitted_at' => 'datetime',
            'triaged_at' => 'datetime',
            'seen_at' => 'datetime',
            'discharge_started_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', EncounterStatus::active());
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_nurse_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ClinicalEntry::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function pendingTransfer(): HasOne
    {
        // The filter goes inside ofMany so it applies before "latest" is chosen.
        return $this->hasOne(Transfer::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('status', Transfer::PENDING));
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class);
    }

    public function latestObservation(): HasOne
    {
        return $this->hasOne(ClinicalEntry::class)
            ->ofMany(['recorded_at' => 'max', 'id' => 'max'], fn ($q) => $q->where('category', EntryCategory::Observation->value));
    }

    public function isActive(): bool
    {
        return in_array($this->status->value, EncounterStatus::active(), true);
    }

    public function isDischargePending(): bool
    {
        return $this->status === EncounterStatus::DischargePending;
    }

    public function waitingMinutes(): int
    {
        return (int) $this->arrived_at->diffInMinutes(now());
    }

    /** A&E: has the patient breached the target time for their triage category? */
    public function isBreachingTarget(): bool
    {
        if ($this->seen_at || ! $this->triage_category) {
            return false;
        }

        return $this->waitingMinutes() > $this->triage_category->targetMinutes();
    }

    /** Effective observation frequency: a ward round override, else the ward default. */
    public function obsFrequencyHours(WardConfig $config): int
    {
        return $this->obs_frequency_hours ?: $config->obs_frequency_hours;
    }

    public function obsDueAt(WardConfig $config): ?Carbon
    {
        $last = $this->latestObservation;

        return $last
            ? $last->recorded_at->copy()->addHours($this->obsFrequencyHours($config))
            : null;
    }
}
