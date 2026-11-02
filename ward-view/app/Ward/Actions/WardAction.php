<?php

namespace App\Ward\Actions;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\ClinicalEntry;
use App\Models\Encounter;
use App\Models\User;

/**
 * One button on the ward action bar. Each button is a coded component
 * registered under a fixed key; wards only switch keys on or off.
 *
 * Related actions are grouped under a single button and a type selector
 * inside the form picks the specific one ("compress, don't hide").
 */
abstract class WardAction
{
    /** Fixed registry key, stored in WardConfig. Never rename a key. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** Everything that sits inside the button, shown under the label. */
    abstract public function contains(): string;

    abstract public function role(): UserRole;

    /** Validation rules for the submitted form. */
    abstract public function rules(Encounter $encounter): array;

    /** Carry out the action. Returns the confirmation message. */
    abstract public function handle(Encounter $encounter, User $user, array $data): string;

    /** Type selector options inside the form: value => label. */
    public function types(): array
    {
        return [];
    }

    /** Types offered for this patient right now (some only make sense in context). */
    public function typesFor(Encounter $encounter): array
    {
        return $this->types();
    }

    /** Ward types this button can be enabled on. */
    public function wardTypes(): array
    {
        return WardType::cases();
    }

    public function supports(WardType $type): bool
    {
        return in_array($type, $this->wardTypes(), true);
    }

    public function icon(): string
    {
        return '•';
    }

    public function view(): string
    {
        return 'actions.'.$this->key();
    }

    /** Extra data the form needs (drug chart, empty beds, teams...). */
    public function viewData(Encounter $encounter): array
    {
        return [];
    }

    /** Null when the action can be used; otherwise why it is greyed out. */
    public function unavailableReason(Encounter $encounter): ?string
    {
        return $encounter->isActive() ? null : 'Patient has left the ward.';
    }

    public function typeRule(): array
    {
        return $this->types() ? ['required', 'in:'.implode(',', array_keys($this->types()))] : ['nullable'];
    }

    public function typeLabel(?string $type): ?string
    {
        return $type ? ($this->types()[$type] ?? $type) : null;
    }

    protected function record(
        Encounter $encounter,
        User $user,
        EntryCategory $category,
        string $summary,
        array $payload = [],
        array $attributes = [],
    ): ClinicalEntry {
        return $encounter->entries()->create(array_merge([
            'user_id' => $user->id,
            'category' => $category,
            'action_key' => $this->key(),
            'summary' => mb_strimwidth($summary, 0, 250, '…'),
            'payload' => array_filter($payload, fn ($v) => $v !== null && $v !== '' && $v !== []),
            'recorded_at' => now(),
        ], $attributes));
    }

    /** A&E: the first doctor entry marks the patient as seen. */
    protected function markSeen(Encounter $encounter, User $user): void
    {
        if ($user->isDoctor() && ! $encounter->seen_at) {
            $encounter->update(['seen_at' => now()]);
        }
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'contains' => $this->contains(),
            'role' => $this->role()->value,
            'types' => $this->types(),
        ];
    }
}
