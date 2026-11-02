<?php

namespace App\Models;

use App\Enums\DnrStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'mrn', 'name', 'date_of_birth', 'sex', 'allergies', 'dnr_status', 'critical_flag', 'critical_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'allergies' => 'array',
            'dnr_status' => DnrStatus::class,
            'critical_flag' => 'boolean',
        ];
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function age(): int
    {
        return $this->date_of_birth->age;
    }

    public function hasAllergies(): bool
    {
        return ! empty($this->allergies) && $this->allergies !== ['NKDA'];
    }
}
