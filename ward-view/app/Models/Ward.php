<?php

namespace App\Models;

use App\Enums\EncounterStatus;
use App\Enums\WardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ward extends Model
{
    protected $fillable = ['name', 'code', 'specialty', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function config(): HasOne
    {
        return $this->hasOne(WardConfig::class);
    }

    public function configChanges(): HasMany
    {
        return $this->hasMany(WardConfigChange::class)->latest('id');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class)->orderBy('position');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function activeEncounters(): HasMany
    {
        return $this->encounters()->whereIn('status', EncounterStatus::active());
    }

    public function handoverNotes(): HasMany
    {
        return $this->hasMany(HandoverNote::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WardTask::class);
    }

    public function type(): ?WardType
    {
        return $this->config?->ward_type;
    }
}
