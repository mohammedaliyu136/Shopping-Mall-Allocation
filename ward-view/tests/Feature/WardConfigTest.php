<?php

namespace Tests\Feature;

use App\Enums\WardType;
use App\Models\Patient;
use App\Models\Ward;
use App\Models\WardConfig;
use App\Models\WardConfigChange;
use App\Services\WardConfigRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WardConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHospital();
    }

    private function validInput(Ward $ward, array $override = []): array
    {
        $c = $ward->config;

        return array_merge([
            'name' => $ward->name, 'is_active' => 1,
            'ward_type' => $c->ward_type->value, 'bed_layout' => $c->bed_layout->value, 'bed_count' => $c->bed_count,
            'obs_chart_type' => $c->obs_chart_type->value, 'obs_frequency_hours' => $c->obs_frequency_hours,
            'rollout_stage' => $c->rollout_stage->value, 'enabled_tabs' => $c->enabled_tabs,
            'doctor_actions' => $c->doctor_actions, 'nurse_actions' => $c->nurse_actions,
        ], $override);
    }

    public function test_a_new_ward_works_immediately_with_its_type_defaults(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('admin.wards.store'), ['name' => 'Maternity Ward', 'code' => 'MAT', 'ward_type' => 'general'])
            ->assertRedirect();

        $ward = Ward::where('code', 'MAT')->first();
        $this->assertSame(WardType::General, $ward->config->ward_type);
        $this->assertSame(24, $ward->beds()->count());
        $this->assertContains('refer_consult_transfer', $ward->config->doctor_actions);
        $this->assertSame(1, $ward->configChanges()->count());

        // Clinical staff can open it straight away: no code changes needed.
        $this->onWard('dr.okafor', 'MAT')->get(route('wards.show', $ward))->assertOk()->assertSee('Maternity Ward');
        $this->onWard('dr.okafor', 'MAT')->get(route('wards.patients', $ward))->assertOk()->assertSee('Bay 4');
    }

    public function test_each_ward_type_gets_its_own_defaults(): void
    {
        $this->actingAs($this->user('admin'))->post(route('admin.wards.store'), ['name' => 'Neonatal ICU', 'code' => 'NICU', 'ward_type' => 'critical_care']);
        $this->actingAs($this->user('admin'))->post(route('admin.wards.store'), ['name' => 'Paediatric A&E', 'code' => 'PAE', 'ward_type' => 'emergency']);

        $icu = Ward::where('code', 'NICU')->first()->config;
        $this->assertContains('critical_care', $icu->enabled_tabs);
        $this->assertSame(1, $icu->obs_frequency_hours);

        $ae = Ward::where('code', 'PAE')->first()->config;
        $this->assertContains('triage', $ae->nurse_actions);
        $this->assertContains('disposition', $ae->doctor_actions);
        $this->assertNotContains('discharge', $ae->doctor_actions);
    }

    public function test_admin_screen_is_restricted_to_admins(): void
    {
        $ward = $this->ward('MWA');
        foreach (['dr.okafor', 'nurse.bello'] as $login) {
            $this->actingAs($this->user($login))->get(route('admin.wards.index'))->assertForbidden();
            $this->actingAs($this->user($login))->get(route('admin.wards.edit', $ward))->assertForbidden();
            $this->actingAs($this->user($login))->put(route('admin.wards.update', $ward), $this->validInput($ward))->assertForbidden();
        }
        $this->actingAs($this->user('admin'))->get(route('admin.wards.index'))->assertOk()->assertSee('Medical Ward A');
        $this->actingAs($this->user('admin'))->get(route('admin.wards.edit', $ward))->assertOk()->assertSee('Refer, Consult, Transfer');
    }

    public function test_every_change_is_logged_and_bumps_the_version(): void
    {
        $ward = $this->ward('MWA');
        $before = $ward->config->version;

        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward, [
            'nurse_actions' => array_values(array_diff($ward->config->nurse_actions, ['collect_sample'])),
            'obs_frequency_hours' => 6,
        ]))->assertRedirect();

        $config = $ward->config()->first();
        $this->assertSame($before + 1, $config->version);
        $this->assertNotContains('collect_sample', $config->nurse_actions);

        $change = WardConfigChange::where('ward_id', $ward->id)->latest('id')->first();
        $this->assertSame('Ward Systems Admin', $change->user_name);
        $this->assertArrayHasKey('nurse_actions', $change->diff());
        $this->assertSame([4, 6], $change->diff()['obs_frequency_hours']);

        $this->actingAs($this->user('admin'))->get(route('admin.wards.history', $ward))->assertOk()->assertSee('collect_sample');

        // Saving with no change does not create a new version.
        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward->fresh()));
        $this->assertSame($before + 1, $ward->config()->first()->version);
    }

    public function test_change_log_is_append_only(): void
    {
        $this->expectException(\RuntimeException::class);
        WardConfigChange::first()->delete();
    }

    public function test_buttons_not_valid_for_the_ward_type_are_dropped(): void
    {
        $ward = $this->ward('MWA');
        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward, [
            'doctor_actions' => ['ward_round_note', 'disposition', 'record_observations'],
            'nurse_actions' => ['triage', 'nursing_note'],
            'enabled_tabs' => ['history', 'critical_care'],
        ]));

        $c = $ward->config()->first();
        $this->assertSame(['ward_round_note'], $c->doctor_actions);
        $this->assertSame(['nursing_note'], $c->nurse_actions);
        $this->assertSame(['history'], $c->enabled_tabs);
    }

    public function test_changing_type_with_apply_defaults_switches_to_that_types_template(): void
    {
        $ward = $this->ward('SWB');
        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward, [
            'ward_type' => 'critical_care', 'apply_defaults' => 1, 'bed_layout' => 'pods', 'bed_count' => 18,
        ]));

        $c = $ward->config()->first();
        $this->assertSame(WardType::CriticalCare, $c->ward_type);
        $this->assertContains('critical_care', $c->enabled_tabs);
        $this->assertSame('hourly', $c->obs_chart_type->value);
        $this->assertSame('Pod 1', $ward->beds()->first()->group_label);
    }

    public function test_reducing_bed_count_never_removes_an_occupied_bed(): void
    {
        $ward = $this->ward('MWA'); // Patient in bed 7
        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward, ['bed_count' => 4]));

        $this->assertSame(5, $ward->beds()->where('is_active', true)->count());
        $this->assertTrue($ward->beds()->where('position', 7)->first()->is_active);
        $this->assertFalse($ward->beds()->where('position', 8)->first()->is_active);
    }

    public function test_config_is_cached_and_refreshed_only_when_an_admin_saves(): void
    {
        $ward = $this->ward('MWA');
        $repo = app(WardConfigRepository::class);
        $repo->for($ward);

        DB::enableQueryLog();
        $repo->for($ward);
        $repo->for($ward->id);
        $this->assertEmpty(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'ward_configs')), 'Config should come from the cache.');
        DB::disableQueryLog();

        // A direct DB change is not seen until the admin path refreshes the cache.
        WardConfig::where('ward_id', $ward->id)->update(['obs_frequency_hours' => 12]);
        $this->assertSame(4, $repo->for($ward)->obs_frequency_hours);

        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward->fresh(), ['obs_frequency_hours' => 2]));
        $this->assertSame(2, $repo->for($ward)->obs_frequency_hours);
    }

    public function test_client_config_endpoint_uses_the_version_as_an_etag(): void
    {
        $ward = $this->ward('ICU');
        $res = $this->onWard('nurse.yusuf')->getJson(route('wards.config', $ward))->assertOk()
            ->assertJsonPath('config.ward_type', 'critical_care')
            ->assertJsonPath('buttons.nurse.0.key', 'record_observations');
        $etag = $res->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->onWard('nurse.yusuf')->get(route('wards.config', $ward), ['If-None-Match' => $etag])->assertStatus(304);

        $this->actingAs($this->user('admin'))->put(route('admin.wards.update', $ward), $this->validInput($ward, ['bed_count' => 10]));
        $this->onWard('nurse.yusuf')->get(route('wards.config', $ward), ['If-None-Match' => $etag])->assertOk();
    }

    public function test_map_ward_types_command_migrates_existing_wards(): void
    {
        $a = Ward::create(['name' => 'Old ITU', 'code' => 'ITU2']);
        $b = Ward::create(['name' => 'Casualty', 'code' => 'CAS']);
        $c = Ward::create(['name' => 'Ward 7 (Gynae)', 'code' => 'W7']);
        $d = Ward::create(['name' => 'Annex', 'code' => 'ANX']);

        $this->artisan('wards:map-types', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($a->config()->first());

        $this->artisan('wards:map-types', ['--pilot' => ['W7'], '--type' => ['ANX=critical_care']])->assertSuccessful();
        $this->assertSame(WardType::CriticalCare, $a->config()->first()->ward_type);
        $this->assertSame(WardType::Emergency, $b->config()->first()->ward_type);
        $this->assertSame(WardType::General, $c->config()->first()->ward_type);
        $this->assertSame(WardType::CriticalCare, $d->config()->first()->ward_type);
        $this->assertSame('pilot', $c->config()->first()->rollout_stage->value);
        $this->assertSame('not_started', $a->config()->first()->rollout_stage->value);

        // Not-started wards are hidden from clinical staff's ward list.
        $this->actingAs($this->user('nurse.bello'))->get(route('wards.index'))->assertOk()->assertDontSee('Old ITU')->assertSee('Ward 7 (Gynae)');
    }
}
