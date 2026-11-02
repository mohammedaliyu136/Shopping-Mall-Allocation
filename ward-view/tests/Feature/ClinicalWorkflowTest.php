<?php

namespace Tests\Feature;

use App\Enums\EncounterStatus;
use App\Enums\EntryCategory;
use App\Models\Bed;
use App\Models\Encounter;
use App\Models\Team;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHospital();
    }

    private function act(string $login, Encounter $e, string $key, array $data, ?string $ward = null)
    {
        return $this->onWard($login, $ward)->post(route('actions.store', [$e, $key]), $data);
    }

    private function vitals(array $o = []): array
    {
        return $o + ['respiratory_rate' => 16, 'spo2' => 97, 'on_oxygen' => 0, 'systolic_bp' => 122, 'diastolic_bp' => 78,
            'heart_rate' => 76, 'consciousness' => 'A', 'temperature' => 36.9, 'pain_score' => 1, 'fluid_in_ml' => 250, 'fluid_out_ml' => 200];
    }

    // ---------- Doctor ----------

    public function test_ward_round_note_updates_problem_list_obs_frequency_and_nursing_tasks(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->act('dr.okafor', $e, 'ward_round_note', [
            'progress' => 'Afebrile, eating well.', 'plan' => 'Oral switch tomorrow',
            'diagnoses' => "Community-acquired pneumonia\nType 2 diabetes\nHypokalaemia",
            'nursing_instructions' => 'Daily weights', 'diet' => 'diabetic', 'obs_frequency_hours' => 6,
        ])->assertRedirect(route('encounters.show', $e))->assertSessionHas('status');

        $e->refresh();
        $this->assertContains('Hypokalaemia', $e->diagnoses);
        $this->assertSame(6, $e->obs_frequency_hours);
        $this->assertDatabaseHas('ward_tasks', ['encounter_id' => $e->id, 'title' => 'Daily weights', 'for_role' => 'nurse']);
        $note = $e->entries()->where('action_key', 'ward_round_note')->latest('id')->first();
        $this->assertSame('Diabetic diet', $note->data('diet'));
    }

    public function test_validation_errors_reopen_the_same_form(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->onWard('dr.okafor')->from(route('encounters.show', $e))
            ->post(route('actions.store', [$e, 'ward_round_note']), ['progress' => ''])
            ->assertSessionHasErrors('progress')->assertSessionHas('open_action', 'ward_round_note');
    }

    public function test_prescribe_new_stop_modify_fluids_and_transfusion(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->act('dr.okafor', $e, 'prescribe', ['type' => 'new', 'drug' => 'Paracetamol', 'dose' => '1 g', 'route' => 'PO', 'frequency' => 'QDS'])->assertSessionHasNoErrors();
        $rx = $e->entries()->where('payload->drug', 'Paracetamol')->first();
        $this->assertSame('active', $rx->status);

        $this->act('dr.okafor', $e, 'prescribe', ['type' => 'stop_modify', 'prescription_id' => $rx->id, 'change' => 'modify', 'new_dose' => '500 mg', 'reason' => 'Low weight'])->assertSessionHasNoErrors();
        $this->assertSame('superseded', $rx->fresh()->status);
        $modified = $e->entries()->where('parent_id', $rx->id)->first();
        $this->assertSame('500 mg', $modified->data('dose'));

        $this->act('dr.okafor', $e, 'prescribe', ['type' => 'stop_modify', 'prescription_id' => $modified->id, 'change' => 'stop', 'reason' => 'Pain settled'])->assertSessionHasNoErrors();
        $this->assertSame('stopped', $modified->fresh()->status);

        $this->act('dr.okafor', $e, 'prescribe', ['type' => 'iv_fluids', 'fluid' => '0.9% Sodium chloride', 'volume_ml' => 1000, 'rate_ml_hr' => 125])->assertSessionHasNoErrors();
        $this->act('dr.okafor', $e, 'prescribe', ['type' => 'transfusion', 'product' => 'red_cells', 'units' => 2, 'transfusion_indication' => 'Hb 65'])->assertSessionHasNoErrors();
        $this->assertSame(2, $e->entries()->whereIn('type', ['iv_fluids', 'transfusion'])->count());

        $this->act('dr.okafor', $e, 'prescribe', ['type' => 'new', 'drug' => ''])->assertSessionHasErrors(['drug', 'dose']);

        $this->onWard('dr.okafor')->get(route('encounters.tab', [$e, 'medication']))->assertOk()->assertSee('Transfuse 2 unit(s) Red cells')->assertSee('Stopped / superseded');
    }

    public function test_lab_request_flows_to_collect_sample_and_result(): void
    {
        $e = $this->encounterFor('Blessing Eze');
        $this->act('dr.okafor', $e, 'request', ['type' => 'lab', 'tests' => ['FBC', 'Blood cultures'], 'other_test' => 'G6PD', 'urgency' => 'urgent', 'clinical_details' => 'Fever'])->assertSessionHasNoErrors();
        $this->act('dr.okafor', $e, 'request', ['type' => 'radiology', 'item' => 'Chest X-ray', 'urgency' => 'routine', 'clinical_details' => 'Cough'])->assertSessionHasNoErrors();

        $labs = $e->entries()->where('type', 'lab')->where('status', 'requested')->get();
        $this->assertCount(3, $labs);
        $this->onWard('nurse.bello')->get(route('actions.form', [$e, 'collect_sample']))->assertOk()->assertSee('G6PD');

        $this->act('nurse.bello', $e, 'collect_sample', ['request_ids' => $labs->pluck('id')->all(), 'collected_at' => now()->subMinutes(5)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->assertSame(0, $e->entries()->where('type', 'lab')->where('status', 'requested')->count());

        // Nothing left to collect: the button is greyed out with a reason.
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertSee('No lab requests waiting for a sample.');

        $this->onWard('dr.okafor')->post(route('entries.result', $labs->first()), ['result' => 'Hb 98', 'abnormal' => 1])->assertRedirect();
        $this->onWard('dr.okafor')->get(route('encounters.tab', [$e, 'investigations']))->assertSee('Hb 98')->assertSee('Chest X-ray');
    }

    public function test_procedure_order_and_theatre_booking(): void
    {
        $e = $this->encounterFor('Emeka Obi');
        $this->act('dr.sani', $e, 'procedure', ['type' => 'order', 'procedure' => 'NG tube insertion', 'urgency' => 'routine', 'indication' => 'Decompression'], 'SWB')->assertSessionHasNoErrors();
        $this->act('dr.sani', $e, 'procedure', ['type' => 'theatre', 'procedure' => 'Laparotomy', 'urgency' => 'urgent', 'indication' => 'Perforation', 'consent' => 1, 'theatre_date' => now()->toDateString(), 'anaesthetic' => 'general', 'fasting_from' => '02:00'], 'SWB')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ward_tasks', ['encounter_id' => $e->id, 'for_role' => 'nurse']);
        $this->onWard('dr.sani', 'SWB')->get(route('encounters.tab', [$e, 'procedure']))->assertSee('Laparotomy')->assertSee('NG tube insertion');
    }

    public function test_consult_keeps_the_patient_with_the_current_team(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $team = $e->team_id;
        $cardio = Team::where('name', 'Cardiology')->first();
        $this->act('dr.okafor', $e, 'refer_consult_transfer', ['type' => 'consult', 'team_id' => $cardio->id, 'question' => 'AF management?', 'urgency' => 'routine'])->assertSessionHasNoErrors();
        $this->assertSame($team, $e->fresh()->team_id);
        $this->assertSame(0, $e->transfers()->count());
    }

    public function test_transfer_to_team_moves_ownership_immediately(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $resp = Team::where('name', 'Respiratory')->first();
        $this->act('dr.okafor', $e, 'refer_consult_transfer', ['type' => 'transfer_team', 'team_id' => $resp->id, 'reason' => 'Respiratory lead', 'urgency' => 'routine'])->assertSessionHasNoErrors();
        $this->assertSame($resp->id, $e->fresh()->team_id);
        $this->assertSame(Transfer::COMPLETED, $e->transfers()->first()->status);
    }

    public function test_transfer_to_ward_waits_for_the_receiving_nurse(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $swb = $this->ward('SWB');
        $this->act('dr.okafor', $e, 'refer_consult_transfer', ['type' => 'transfer_ward', 'to_ward_id' => $swb->id, 'reason' => 'Surgical review', 'urgency' => 'routine'])->assertSessionHasNoErrors();
        $this->assertSame($this->ward('MWA')->id, $e->fresh()->ward_id);

        // Requesting a second transfer is refused while one is pending.
        $this->act('dr.okafor', $e, 'refer_consult_transfer', ['type' => 'transfer_ward', 'to_ward_id' => $swb->id, 'reason' => 'again', 'urgency' => 'routine'])->assertSessionHasErrors('to_ward_id');

        // It shows as incoming on the receiving ward.
        $this->onWard('nurse.lawal')->get(route('wards.patients', $swb))->assertSee('Incoming patients')->assertSee('Abubakar Musa');

        // The sending ward's nurse cannot accept it.
        $this->onWard('nurse.bello')->get(route('actions.form', [$e, 'move_patient']))->assertOk()->assertDontSee('Accept transfer onto this ward');

        // The receiving nurse sees only "accept" and picks a bed on their ward.
        $this->onWard('nurse.lawal')->get(route('encounters.show', $e))->assertOk()->assertSee('Move Patient')->assertDontSee('Administer Medication');
        $this->onWard('nurse.lawal')->get(route('actions.form', [$e, 'move_patient']))->assertOk()->assertSee('Accept transfer onto this ward');
        $bed = $swb->beds()->where('position', 10)->first();
        $this->act('nurse.lawal', $e, 'move_patient', ['type' => 'accept_transfer', 'bed_id' => $bed->id])->assertSessionHasNoErrors();

        $e->refresh();
        $this->assertSame($swb->id, $e->ward_id);
        $this->assertSame($bed->id, $e->bed_id);
        $this->assertSame(Transfer::COMPLETED, $e->transfers()->first()->status);
    }

    public function test_escalation_to_icu_flags_the_patient_and_cancelling_clears_it(): void
    {
        $e = $this->encounterFor('Hauwa Ibrahim');
        $this->act('dr.okafor', $e, 'refer_consult_transfer', ['type' => 'escalate', 'to_ward_id' => $this->ward('ICU')->id, 'reason' => 'NEWS2 11, type 1 RF', 'urgency' => 'stat'])->assertSessionHasNoErrors();
        $this->assertTrue($e->patient->fresh()->critical_flag);
        $this->assertSame(Transfer::TYPE_ESCALATION, $e->pendingTransfer->type);
        $this->onWard('dr.okafor')->get(route('encounters.show', $e))->assertSee('Transfer pending → Intensive Care Unit');

        $this->onWard('dr.okafor')->post(route('transfers.cancel', $e->pendingTransfer))->assertRedirect();
        $this->assertNull($e->fresh()->pendingTransfer);
    }

    public function test_discharge_opens_the_nurse_checklist_and_completing_it_frees_the_bed(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $bed = $e->bed_id;
        $this->act('dr.okafor', $e, 'discharge', ['type' => 'home', 'summary' => 'CAP treated', 'follow_up' => 'GP 1 week'])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame(EncounterStatus::DischargePending, $e->status);

        // Checklist appears for nurses only; Discharge greys out for the doctor.
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertSee('Discharge checklist');
        $this->onWard('dr.okafor')->get(route('encounters.show', $e))->assertDontSee('Discharge checklist')->assertSee('Discharge already started');

        $items = array_keys($e->discharge_checklist);
        $this->onWard('nurse.bello')->post(route('encounters.checklist', $e), ['items' => array_slice($items, 0, 2), 'complete' => 1]);
        $this->assertSame(EncounterStatus::DischargePending, $e->fresh()->status, 'Cannot complete with items left.');

        $this->onWard('nurse.bello')->post(route('encounters.checklist', $e), ['items' => $items, 'complete' => 1])->assertRedirect(route('wards.show', $e->ward_id));
        $e->refresh();
        $this->assertSame(EncounterStatus::Discharged, $e->status);
        $this->assertNull($e->bed_id);
        $this->assertNull(Bed::find($bed)->occupant);
    }

    public function test_discharge_absorbs_deceased_with_its_own_checklist(): void
    {
        $e = $this->encounterFor('Hauwa Ibrahim');
        $this->act('dr.okafor', $e, 'discharge', ['type' => 'deceased', 'time_of_death' => now()->subMinutes(10)->format('Y-m-d\TH:i'), 'cause_of_death' => 'Cardiac failure'])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame('deceased', $e->discharge_type);
        $this->assertArrayHasKey('last_offices', $e->discharge_checklist);

        $this->act('dr.okafor', $e, 'discharge', ['type' => 'facility'])->assertSessionHasErrors();
    }

    public function test_nurse_cannot_tick_checklist_before_the_doctor_starts_a_discharge(): void
    {
        $this->onWard('nurse.bello')->post(route('encounters.checklist', $this->encounterFor('Abubakar Musa')), ['items' => []])->assertForbidden();
    }

    public function test_header_badges_are_editable_by_the_right_role(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->onWard('nurse.bello')->post(route('encounters.header', [$e, 'resus']), ['dnr_status' => 'dnacpr'])->assertForbidden();
        $this->onWard('dr.okafor')->post(route('encounters.header', [$e, 'resus']), ['dnr_status' => 'dnacpr', 'reason' => 'Discussed with family'])->assertRedirect();
        $this->assertSame('dnacpr', $e->patient->fresh()->dnr_status->value);

        $this->onWard('nurse.bello')->post(route('encounters.header', [$e, 'allergies']), ['allergies' => 'Penicillin (rash), Latex'])->assertRedirect();
        $this->assertSame(['Penicillin (rash)', 'Latex'], $e->patient->fresh()->allergies);

        $this->onWard('dr.okafor')->post(route('encounters.header', [$e, 'critical']), ['critical_flag' => 1, 'critical_reason' => 'Sepsis'])->assertRedirect();
        $this->assertTrue($e->patient->fresh()->critical_flag);

        $this->assertSame(3, $e->entries()->where('action_key', 'header')->count());
    }

    public function test_cosign_is_inline_and_not_allowed_on_your_own_entry(): void
    {
        $entry = $this->encounterFor('Abubakar Musa')->entries()->where('action_key', 'ward_round_note')->first();
        $this->onWard('dr.okafor')->post(route('entries.cosign', $entry))->assertForbidden();
        $this->onWard('dr.sani', 'MWA')->post(route('entries.cosign', $entry))->assertRedirect();
        $this->assertNotNull($entry->fresh()->cosigned_at);
    }

    // ---------- Nurse ----------

    public function test_record_observations_calculates_news2_and_prompts_escalation(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->act('nurse.bello', $e, 'record_observations', $this->vitals())->assertSessionHasNoErrors()->assertSessionMissing('escalate');
        $this->assertSame(0, $e->entries()->where('category', 'observation')->latest('id')->first()->score);

        $this->act('nurse.bello', $e, 'record_observations', $this->vitals(['respiratory_rate' => 27, 'spo2' => 90, 'on_oxygen' => 1, 'heart_rate' => 125]))
            ->assertSessionHas('escalate');
        $obs = $e->entries()->where('category', 'observation')->latest('id')->first();
        $this->assertSame(10, $obs->score);
        $this->assertSame('high', $obs->status);

        $this->onWard('nurse.bello')->get(route('encounters.tab', [$e, 'observations']))->assertOk()->assertSee('Fluid balance today');
    }

    public function test_latest_observation_ignores_newer_non_observation_entries(): void
    {
        $e = $this->encounterFor('Hauwa Ibrahim');
        $this->act('nurse.bello', $e, 'record_observations', $this->vitals());
        $this->travel(1)->minutes();
        $this->act('nurse.bello', $e, 'nursing_note', ['type' => 'shift', 'note' => 'Settled']);
        $this->assertNotNull($e->fresh()->latestObservation);
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertDontSee('No observations yet')->assertSee('NEWS2 0');

        // A completed transfer newer than a pending one must not hide it.
        $pending = $e->transfers()->create(['type' => 'ward', 'from_ward_id' => $e->ward_id, 'to_ward_id' => $this->ward('SWB')->id, 'requested_by' => $this->user('dr.okafor')->id, 'status' => 'pending']);
        $e->transfers()->create(['type' => 'team', 'from_ward_id' => $e->ward_id, 'requested_by' => $this->user('dr.okafor')->id, 'status' => 'completed']);
        $this->assertTrue($pending->is($e->fresh()->pendingTransfer));
    }

    public function test_critical_care_obs_record_ventilator_and_infusion_rates(): void
    {
        $e = $this->encounterFor('Yakubu Garba');
        $nor = $e->entries()->where('payload->drug', 'Noradrenaline')->first();
        $this->onWard('nurse.yusuf')->get(route('actions.form', [$e, 'record_observations']))->assertOk()->assertSee('Ventilator')->assertSee('Noradrenaline');

        $this->act('nurse.yusuf', $e, 'record_observations', $this->vitals(['consciousness' => 'U', 'on_oxygen' => 1, 'vent_mode' => 'simv', 'fio2' => 35, 'peep' => 6, 'infusion_rates' => [$nor->id => '4 ml/hr']]))->assertSessionHasNoErrors();
        $obs = $e->entries()->where('category', 'observation')->latest('id')->first();
        $this->assertSame(35, $obs->data('ventilator.fio2'));
        $this->assertSame('4 ml/hr', $obs->data('infusions.0.rate'));

        // General wards do not get the critical care sections.
        $this->onWard('nurse.bello')->get(route('actions.form', [$this->encounterFor('Abubakar Musa'), 'record_observations']))->assertDontSee('Infusions running');
    }

    public function test_administer_medication_given_delayed_and_missed(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $rx = $e->entries()->where('payload->drug', 'Metformin')->first();
        $this->act('nurse.bello', $e, 'administer_medication', ['type' => 'given', 'prescription_id' => $rx->id, 'given_at' => now()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->act('nurse.bello', $e, 'administer_medication', ['type' => 'delayed', 'prescription_id' => $rx->id, 'reason' => 'nbm', 'delay_until' => '14:00'])->assertSessionHasNoErrors();
        $this->act('nurse.bello', $e, 'administer_medication', ['type' => 'missed', 'prescription_id' => $rx->id, 'reason' => 'refused'])->assertSessionHasNoErrors();
        $this->act('nurse.bello', $e, 'administer_medication', ['type' => 'missed', 'prescription_id' => $rx->id])->assertSessionHasErrors('reason');
        $this->assertSame(['given', 'delayed', 'missed'], $rx->children()->orderBy('id')->pluck('status')->all());
    }

    public function test_administer_is_unavailable_with_an_empty_drug_chart(): void
    {
        $e = $this->encounterFor('Chinedu Nwosu');
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertSee('Nothing on the drug chart yet.');
        $this->act('nurse.bello', $e, 'administer_medication', [])->assertSessionHasErrors('action');
    }

    public function test_nursing_note_types(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->act('nurse.bello', $e, 'nursing_note', ['type' => 'care', 'note' => 'Washed and repositioned', 'care_items' => ['hygiene', 'repositioned']])->assertSessionHasNoErrors();
        $this->act('nurse.bello', $e, 'nursing_note', ['type' => 'wound', 'note' => 'Sacral dressing changed', 'wound_site' => 'Sacrum', 'wound_appearance' => 'clean', 'next_dressing' => now()->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->act('nurse.bello', $e, 'nursing_note', ['type' => 'wound', 'note' => 'x'])->assertSessionHasErrors('wound_site');
        $this->assertDatabaseHas('ward_tasks', ['encounter_id' => $e->id, 'title' => 'Redress wound (Sacrum)']);
    }

    public function test_alert_doctor_shows_in_header_until_acknowledged(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->act('nurse.bello', $e, 'alert_doctor', ['type' => 'review', 'urgency' => 'urgent', 'situation' => 'New chest pain'])->assertSessionHasNoErrors();
        $this->onWard('dr.okafor')->get(route('encounters.show', $e))->assertSee('1 open alert for doctor');

        $alert = $e->entries()->where('category', EntryCategory::Alert->value)->latest('id')->first();
        $this->onWard('nurse.bello')->post(route('entries.acknowledge', $alert))->assertForbidden();
        $this->onWard('dr.okafor')->post(route('entries.acknowledge', $alert), ['response' => 'Coming now'])->assertRedirect();
        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->onWard('dr.okafor')->get(route('encounters.show', $e))->assertDontSee('open alert');
    }

    public function test_move_patient_bed_change_and_off_ward_round_trip(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $occupied = $this->encounterFor('Hauwa Ibrahim')->bed_id;
        $this->act('nurse.bello', $e, 'move_patient', ['type' => 'bed_change', 'bed_id' => $occupied])->assertSessionHasErrors('bed_id');

        $free = $this->ward('MWA')->beds()->where('position', 12)->first();
        $this->act('nurse.bello', $e, 'move_patient', ['type' => 'bed_change', 'bed_id' => $free->id])->assertSessionHasNoErrors();
        $this->assertSame($free->id, $e->fresh()->bed_id);

        $this->act('nurse.bello', $e, 'move_patient', ['type' => 'off_ward', 'destination' => 'theatre', 'escort' => 'Porter'])->assertSessionHasNoErrors();
        $this->assertSame('Theatre', $e->fresh()->off_ward);
        $this->onWard('nurse.bello')->get(route('actions.form', [$e, 'move_patient']))->assertSee('Returned to ward')->assertDontSee('Send for scan / theatre');
        $this->act('nurse.bello', $e, 'move_patient', ['type' => 'returned'])->assertSessionHasNoErrors();
        $this->assertNull($e->fresh()->off_ward);
    }

    public function test_risk_assessments_update_header_badges(): void
    {
        $e = $this->encounterFor('Abubakar Musa');
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertSee('Falls assessment due');
        $this->onWard('dr.okafor')->post(route('encounters.risk', [$e, 'falls']), ['score' => 10])->assertForbidden();
        $this->onWard('nurse.bello')->post(route('encounters.risk', [$e, 'falls']), ['score' => 50])->assertRedirect();
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertDontSee('Falls assessment due')->assertSee('Falls risk: high');
        $this->travel(25)->hours();
        $this->onWard('nurse.bello')->get(route('encounters.show', $e))->assertSee('Falls assessment overdue');
    }

    // ---------- Emergency ----------

    public function test_emergency_triage_waiting_time_and_disposition_admit(): void
    {
        $ae = $this->ward('AE');
        $this->onWard('nurse.danjuma')->post(route('wards.arrivals', $ae), [
            'name' => 'Musa Danladi', 'date_of_birth' => '1970-01-01', 'sex' => 'male', 'presenting_complaint' => 'Shortness of breath',
        ])->assertRedirect();
        $e = $this->encounterFor('Musa Danladi');
        $this->assertSame(EncounterStatus::Waiting, $e->status);

        $this->act('nurse.danjuma', $e, 'triage', ['triage_category' => 2, 'presenting_complaint' => 'SOB, SpO2 88%'])->assertSessionHasNoErrors();
        $this->assertSame(2, $e->fresh()->triage_category->value);

        $this->travel(15)->minutes();
        $this->assertTrue($e->fresh()->isBreachingTarget());
        $this->onWard('nurse.danjuma')->get(route('wards.patients', $ae))->assertSee('over 10m target');

        // Disposition: admit, then the medical ward nurse accepts.
        $this->act('dr.eze', $e, 'disposition', ['type' => 'admit', 'to_ward_id' => $this->ward('MWA')->id, 'team_id' => Team::where('name', 'Respiratory')->value('id'), 'diagnosis' => 'COPD exacerbation'])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertNotNull($e->seen_at);
        $this->assertSame(Transfer::TYPE_ADMISSION, $e->pendingTransfer->type);
        $this->onWard('dr.eze')->get(route('encounters.show', $e))->assertSee('waiting for the ward to accept');

        $bed = $this->ward('MWA')->beds()->where('position', 15)->first();
        $this->act('nurse.bello', $e, 'move_patient', ['type' => 'accept_transfer', 'bed_id' => $bed->id])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame(EncounterStatus::Admitted, $e->status);
        $this->assertNotNull($e->admitted_at);
        $this->assertSame($this->ward('MWA')->id, $e->ward_id);
    }

    public function test_emergency_disposition_discharge_and_refer_close_the_attendance(): void
    {
        $a = $this->encounterFor('Peter Okon');
        $this->act('dr.eze', $a, 'disposition', ['type' => 'discharge', 'diagnosis' => 'Simple laceration'])->assertRedirect(route('wards.show', $this->ward('AE')));
        $this->assertSame(EncounterStatus::Discharged, $a->fresh()->status);

        $b = $this->encounterFor('Mary Johnson');
        $this->act('dr.eze', $b, 'disposition', ['type' => 'refer', 'diagnosis' => 'NOF fracture', 'refer_to' => 'Orthopaedic centre'])->assertSessionHasNoErrors();
        $this->assertSame('referred', $b->fresh()->discharge_type);
    }

    public function test_emergency_has_no_ward_discharge_and_wards_have_no_triage(): void
    {
        $this->onWard('dr.eze')->get(route('actions.form', [$this->encounterFor('Ikenna Uche'), 'discharge']))->assertForbidden();
        $this->onWard('nurse.bello')->get(route('actions.form', [$this->encounterFor('Abubakar Musa'), 'triage']))->assertForbidden();
    }

    public function test_doctor_entry_in_ae_marks_patient_as_seen(): void
    {
        $e = $this->encounterFor('Sani Bala');
        $this->assertNull($e->seen_at);
        $this->act('dr.eze', $e, 'ward_round_note', ['progress' => 'Seen, viral exanthem'])->assertSessionHasNoErrors();
        $this->assertNotNull($e->fresh()->seen_at);
    }
}
