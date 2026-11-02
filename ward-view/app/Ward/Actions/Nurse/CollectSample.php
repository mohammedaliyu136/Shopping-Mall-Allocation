<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;
use Illuminate\Validation\Rule;

class CollectSample extends WardAction
{
    public function key(): string
    {
        return 'collect_sample';
    }

    public function label(): string
    {
        return 'Collect Sample';
    }

    public function contains(): string
    {
        return 'Mark specimens taken for requested labs';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '🧫';
    }

    private function waiting(Encounter $encounter)
    {
        return $encounter->entries()
            ->where('category', EntryCategory::Investigation->value)
            ->where('type', 'lab')->where('status', 'requested')
            ->orderBy('recorded_at');
    }

    public function viewData(Encounter $encounter): array
    {
        return ['waiting' => $this->waiting($encounter)->with('author')->get()];
    }

    public function unavailableReason(Encounter $encounter): ?string
    {
        if (! $this->waiting($encounter)->exists()) {
            return 'No lab requests waiting for a sample.';
        }

        return parent::unavailableReason($encounter);
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => [Rule::exists('clinical_entries', 'id')->where('encounter_id', $encounter->id)->where('status', 'requested')],
            'collected_at' => ['required', 'date', 'before_or_equal:now'],
            'site' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $requests = $encounter->entries()->whereIn('id', $data['request_ids'])->get();
        foreach ($requests as $request) {
            $request->update([
                'status' => 'collected',
                'payload' => array_merge($request->payload ?? [], [
                    'collected_at' => $data['collected_at'],
                    'collected_by' => $user->name,
                ]),
            ]);
        }

        $names = $requests->map(fn ($r) => $r->data('item'))->implode(', ');
        $this->record($encounter, $user, EntryCategory::Investigation, "Samples taken: {$names}", [
            'items' => $requests->map(fn ($r) => $r->data('item'))->all(),
            'site' => $data['site'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], ['type' => 'sample', 'status' => 'collected', 'recorded_at' => $data['collected_at']]);

        return "Samples marked as taken: {$names}.";
    }
}
