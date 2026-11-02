<?php

namespace App\Ward\Actions;

use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\WardConfig;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The fixed catalogue of action buttons. The order here is the order the
 * buttons appear on the bar.
 */
class ActionRegistry
{
    /** @var array<string, class-string<WardAction>> */
    public const ACTIONS = [
        // Doctor
        'ward_round_note' => Doctor\WardRoundNote::class,
        'prescribe' => Doctor\Prescribe::class,
        'request' => Doctor\RequestInvestigation::class,
        'procedure' => Doctor\Procedure::class,
        'refer_consult_transfer' => Doctor\ReferConsultTransfer::class,
        'discharge' => Doctor\Discharge::class,
        'disposition' => Doctor\Disposition::class,
        // Nurse
        'triage' => Nurse\Triage::class,
        'record_observations' => Nurse\RecordObservations::class,
        'administer_medication' => Nurse\AdministerMedication::class,
        'nursing_note' => Nurse\NursingNote::class,
        'collect_sample' => Nurse\CollectSample::class,
        'alert_doctor' => Nurse\AlertDoctor::class,
        'move_patient' => Nurse\MovePatient::class,
    ];

    /** @var array<string, WardAction> */
    private array $instances = [];

    public function find(string $key): WardAction
    {
        if (! isset(self::ACTIONS[$key])) {
            throw new InvalidArgumentException("Unknown ward action [{$key}].");
        }

        return $this->instances[$key] ??= app(self::ACTIONS[$key]);
    }

    public function has(string $key): bool
    {
        return isset(self::ACTIONS[$key]);
    }

    /** @return Collection<string, WardAction> */
    public function all(): Collection
    {
        return collect(array_keys(self::ACTIONS))->mapWithKeys(fn ($k) => [$k => $this->find($k)]);
    }

    /** Every button a role could have on a given ward type (admin form options). */
    public function available(UserRole $role, WardType $type): Collection
    {
        return $this->all()->filter(fn (WardAction $a) => $a->role() === $role && $a->supports($type));
    }

    /** The buttons a user actually sees on a ward: enabled in config, in registry order. */
    public function enabledFor(WardConfig $config, UserRole $role): Collection
    {
        $enabled = $config->actionsFor($role);

        return $this->available($role, $config->ward_type)
            ->filter(fn (WardAction $a) => in_array($a->key(), $enabled, true));
    }

    /** Keep only keys that exist and are valid for the role and type. */
    public function sanitise(array $keys, UserRole $role, WardType $type): array
    {
        $valid = $this->available($role, $type)->keys()->all();

        return array_values(array_filter($valid, fn ($k) => in_array($k, $keys, true)));
    }
}
