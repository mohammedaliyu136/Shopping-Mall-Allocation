<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Ward\Actions\ActionRegistry;
use App\Ward\Tabs\TabRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WardScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHospital();
    }

    public function test_login_loads_the_home_ward(): void
    {
        $this->post('/login', ['email' => 'nurse.bello@hospital.test', 'password' => 'password'])
            ->assertRedirect(route('wards.show', $this->ward('MWA')))
            ->assertSessionHas('ward_id', $this->ward('MWA')->id);
        $this->post('/logout');
        $this->post('/login', ['email' => 'admin@hospital.test', 'password' => 'password'])->assertRedirect(route('admin.wards.index'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('wards.show', $this->ward('MWA')))->assertRedirect(route('login'));
    }

    public function test_ward_selection_remembers_the_ward(): void
    {
        $this->actingAs($this->user('dr.okafor'))->get(route('wards.index'))->assertOk()->assertSee('Intensive Care Unit')->assertSee('Pilot');
        $this->actingAs($this->user('dr.okafor'))->post(route('wards.select', $this->ward('SWB')))
            ->assertRedirect(route('wards.show', $this->ward('SWB')))->assertSessionHas('ward_id', $this->ward('SWB')->id);
    }

    public function test_ward_shell_renders_before_the_patient_list(): void
    {
        // The shell contains no patient names: the list is a separate request.
        $this->onWard('nurse.bello')->get(route('wards.show', $this->ward('MWA')))
            ->assertOk()->assertSee('data-src', false)->assertDontSee('Abubakar Musa');

        $this->onWard('nurse.bello')->get(route('wards.patients', $this->ward('MWA')))
            ->assertOk()->assertSee('Abubakar Musa')->assertSee('Bay 1')->assertSee('Alert')->assertSee('Discharging')->assertSee('Off ward');
    }

    public function test_patient_list_query_count_does_not_grow_with_patients(): void
    {
        $ward = $this->ward('MWA');
        $this->onWard('nurse.bello')->get(route('wards.patients', $ward)); // warm cache

        DB::enableQueryLog();
        $this->onWard('nurse.bello')->get(route('wards.patients', $ward))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $queries, "Patient list ran {$queries} queries.");
    }

    public function test_each_ward_type_has_its_own_screen(): void
    {
        $this->onWard('nurse.yusuf')->get(route('wards.patients', $this->ward('ICU')))
            ->assertOk()->assertSee('Yakubu Garba')->assertSee('Ventilated')->assertSee('1:1 Nurse Musa Yusuf')->assertSee('SIMV');
        $this->onWard('nurse.yusuf')->get(route('wards.patients', $this->ward('ICU')).'?mine=1')
            ->assertOk()->assertSee('Yakubu Garba')->assertDontSee('Aisha Suleiman');

        $this->onWard('nurse.danjuma')->get(route('wards.show', $this->ward('AE')))->assertOk()->assertSee('Register arrival');
        $this->onWard('nurse.danjuma')->get(route('wards.patients', $this->ward('AE')))
            ->assertOk()->assertSee('Waiting to be seen')->assertSee('Past triage target')->assertSee('Not triaged')->assertSee('Ikenna Uche');
    }

    public function test_doctors_and_nurses_get_their_own_button_bar(): void
    {
        $e = $this->encounterFor('Abubakar Musa');

        $doctor = $this->onWard('dr.okafor')->get(route('encounters.show', $e))->assertOk();
        foreach (['Ward Round Note', 'Prescribe', 'Request', 'Procedure', 'Refer, Consult, Transfer', 'Discharge'] as $label) {
            $doctor->assertSee($label);
        }
        $doctor->assertDontSee('Record Observations')->assertSee('Consult only · transfer to team · transfer to ward · escalate to ICU/HDU');

        $nurse = $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertOk();
        foreach (['Record Observations', 'Administer Medication', 'Nursing Note', 'Collect Sample', 'Alert Doctor', 'Move Patient'] as $label) {
            $nurse->assertSee($label);
        }
        $nurse->assertDontSee('Ward Round Note');

        $this->onWard('admin')->get(route('encounters.show', $e))->assertOk()->assertDontSee('data-action-url', false);
    }

    public function test_status_lives_in_the_header_as_badges(): void
    {
        $this->onWard('dr.okafor')->get(route('encounters.show', $this->encounterFor('Hauwa Ibrahim')))
            ->assertOk()->assertSee('DNACPR')->assertSee('No known drug allergies')->assertSee('1 open alert for doctor')
            ->assertSee('Falls assessment due');
        $this->onWard('dr.okafor')->get(route('encounters.show', $this->encounterFor('Abubakar Musa')))
            ->assertSee('Allergies: Penicillin (rash)');
    }

    public function test_every_enabled_button_opens_in_one_tap_for_every_ward_type(): void
    {
        $registry = app(ActionRegistry::class);
        foreach (['Abubakar Musa' => ['dr.okafor', 'nurse.bello'], 'Yakubu Garba' => ['dr.adeyemi', 'nurse.yusuf'], 'Ikenna Uche' => ['dr.eze', 'nurse.danjuma']] as $patient => $logins) {
            $e = $this->encounterFor($patient);
            $config = $e->ward->config;
            foreach ($logins as $login) {
                $user = $this->user($login);
                foreach ($registry->enabledFor($config, $user->role) as $key => $action) {
                    $this->onWard($login)->get(route('actions.form', [$e, $key]))
                        ->assertOk()->assertSee($action->label());
                }
            }
        }
    }

    public function test_every_enabled_tab_loads_for_every_ward_type(): void
    {
        $tabs = app(TabRegistry::class);
        foreach (['Abubakar Musa', 'Yakubu Garba', 'Ikenna Uche'] as $patient) {
            $e = $this->encounterFor($patient);
            foreach ($tabs->enabledFor($e->ward->config) as $key => $tab) {
                $this->onWard('dr.okafor')->get(route('encounters.tab', [$e, $key]))->assertOk();
            }
        }
        $icu = $this->encounterFor('Yakubu Garba');
        $this->onWard('nurse.yusuf')->get(route('encounters.tab', [$icu, 'critical_care']))->assertOk()->assertSee('Noradrenaline')->assertSee('PEEP');
        $this->onWard('nurse.yusuf')->get(route('encounters.tab', [$icu, 'observations']))->assertOk()->assertSee('Hourly critical care chart');
        $this->onWard('nurse.bello')->get(route('encounters.tab', [$this->encounterFor('Abubakar Musa'), 'history']).'?category=medication')->assertOk()->assertSee('Ceftriaxone')->assertDontSee('Ward round:');
    }

    public function test_disabled_tabs_and_buttons_disappear_and_are_blocked(): void
    {
        $paed = $this->ward('PAED');
        $p = Patient::create(['mrn' => 'P1', 'name' => 'Child Patient', 'date_of_birth' => '2018-01-01', 'sex' => 'female']);
        $e = $paed->encounters()->create(['patient_id' => $p->id, 'status' => 'admitted', 'arrived_at' => now()]);
        $this->onWard('dr.okafor', 'PAED')->get(route('encounters.show', $e))->assertOk()->assertDontSee('>Procedure</a>', false);
        $this->onWard('dr.okafor', 'PAED')->get(route('encounters.tab', [$e, 'procedure']))->assertNotFound();
        $this->onWard('dr.okafor', 'PAED')->get(route('encounters.tab', [$e, 'critical_care']))->assertNotFound();

        $mwa = $this->ward('MWA');
        $mwa->config->update(['nurse_actions' => ['record_observations']]);
        Cache::flush();
        $e1 = $this->encounterFor('Abubakar Musa');
        $this->onWard('nurse.bello')->get(route('encounters.show', $e1))->assertDontSee('Collect Sample');
        $this->onWard('nurse.bello')->get(route('actions.form', [$e1, 'collect_sample']))->assertForbidden();
        $this->onWard('nurse.bello')->post(route('actions.store', [$e1, 'collect_sample']), [])->assertForbidden();
    }

    public function test_roles_cannot_use_each_others_buttons(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->onWard('nurse.bello')->get(route('actions.form', [$e, 'prescribe']))->assertForbidden();
        $this->onWard('dr.okafor')->post(route('actions.store', [$e, 'record_observations']), [])->assertForbidden();
        $this->onWard('admin')->get(route('actions.form', [$e, 'prescribe']))->assertForbidden();
        $this->onWard('dr.okafor')->get(route('actions.form', [$e, 'not_a_button']))->assertNotFound();
    }

    public function test_handover_and_tasks_are_ward_level(): void
    {
        $ward = $this->ward('MWA');
        $this->onWard('nurse.bello')->get(route('handover.index', $ward))->assertOk()->assertSee('Chase echo report')->assertSee('Hauwa Ibrahim — DNACPR');
        $this->onWard('nurse.bello')->post(route('handover.store', $ward), ['shift' => 'day', 'body' => 'All stable.'])->assertRedirect();
        $this->onWard('nurse.bello')->post(route('tasks.store', $ward), ['title' => 'Restock crash trolley', 'for_role' => 'nurse'])->assertRedirect();
        $task = $ward->tasks()->where('title', 'Restock crash trolley')->first();
        $this->onWard('nurse.bello')->post(route('tasks.toggle', [$ward, $task]));
        $this->assertNotNull($task->fresh()->done_at);
    }
}
