<?php

namespace Tests;

use App\Models\Encounter;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\DemoWardSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedHospital(): void
    {
        $this->seed(DemoWardSeeder::class);
    }

    protected function user(string $login): User
    {
        return User::where('email', "{$login}@hospital.test")->firstOrFail();
    }

    protected function ward(string $code): Ward
    {
        return Ward::where('code', $code)->firstOrFail();
    }

    protected function encounterFor(string $patientName): Encounter
    {
        return Encounter::whereHas('patient', fn ($q) => $q->where('name', $patientName))->latest('id')->firstOrFail();
    }

    /** Act as a user who has selected a ward (as the login / ward selection does). */
    protected function onWard(string $login, ?string $wardCode = null): static
    {
        $user = $this->user($login);
        $wardId = $wardCode ? $this->ward($wardCode)->id : $user->ward_id;

        return $this->actingAs($user)->withSession($wardId ? ['ward_id' => $wardId] : []);
    }
}
