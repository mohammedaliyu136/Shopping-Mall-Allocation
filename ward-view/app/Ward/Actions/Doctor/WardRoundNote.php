<?php

namespace App\Ward\Actions\Doctor;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;
use Illuminate\Support\Facades\DB;

/**
 * One note with sections: progress, diagnosis and problem list, nursing
 * instructions, diet and observation frequency.
 */
class WardRoundNote extends WardAction
{
    public function key(): string
    {
        return 'ward_round_note';
    }

    public function label(): string
    {
        return 'Ward Round Note';
    }

    public function contains(): string
    {
        return 'Progress · diagnosis & problems · nursing instructions · diet · obs frequency';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '📝';
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'progress' => ['required', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:3000'],
            'diagnoses' => ['nullable', 'string', 'max:2000'],
            'nursing_instructions' => ['nullable', 'string', 'max:2000'],
            'diet' => ['nullable', 'in:'.implode(',', array_keys(config('ward.diets')))],
            'obs_frequency_hours' => ['nullable', 'in:'.implode(',', array_keys(config('ward.obs_frequencies')))],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        return DB::transaction(function () use ($encounter, $user, $data) {
            $diagnoses = isset($data['diagnoses'])
                ? array_values(array_filter(array_map('trim', preg_split('/\R/', $data['diagnoses']))))
                : null;

            $changes = [];
            if ($diagnoses !== null && $diagnoses !== ($encounter->diagnoses ?? [])) {
                $encounter->diagnoses = $diagnoses;
                $changes[] = 'problem list updated';
            }
            if (! empty($data['obs_frequency_hours']) && (int) $data['obs_frequency_hours'] !== $encounter->obs_frequency_hours) {
                $encounter->obs_frequency_hours = (int) $data['obs_frequency_hours'];
                $changes[] = 'obs '.config('ward.obs_frequencies')[$data['obs_frequency_hours']];
            }
            $encounter->save();

            $this->record($encounter, $user, EntryCategory::Note, 'Ward round: '.$data['progress'], [
                'progress' => $data['progress'],
                'plan' => $data['plan'] ?? null,
                'diagnoses' => $diagnoses,
                'nursing_instructions' => $data['nursing_instructions'] ?? null,
                'diet' => isset($data['diet']) ? config('ward.diets')[$data['diet']] : null,
                'obs_frequency' => isset($data['obs_frequency_hours']) ? config('ward.obs_frequencies')[$data['obs_frequency_hours']] : null,
            ], ['type' => 'ward_round']);

            // Nursing instructions become a task on the nurses' ward list.
            if (! empty($data['nursing_instructions'])) {
                $encounter->ward->tasks()->create([
                    'encounter_id' => $encounter->id,
                    'title' => $data['nursing_instructions'],
                    'for_role' => UserRole::Nurse->value,
                    'created_by' => $user->id,
                ]);
                $changes[] = 'nursing task added';
            }

            $this->markSeen($encounter, $user);

            return 'Ward round note saved'.($changes ? ' ('.implode(', ', $changes).').' : '.');
        });
    }
}
