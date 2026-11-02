<?php

namespace Database\Seeders;

use App\Enums\DnrStatus;
use App\Enums\EncounterStatus;
use App\Enums\EntryCategory;
use App\Enums\RolloutStage;
use App\Enums\TriageCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Team;
use App\Models\User;
use App\Models\Ward;
use App\Services\BedManager;
use App\Services\EarlyWarningScore;
use App\Services\WardConfigRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo hospital: three general wards, ICU, HDU and A&E with staff and patients.
 * Medical Ward A and ICU are the pilot wards from the rollout plan.
 */
class DemoWardSeeder extends Seeder
{
    public function run(): void
    {
        $configs = app(WardConfigRepository::class);
        $news = app(EarlyWarningScore::class);
        $password = Hash::make(env('DEMO_PASSWORD', 'password'));

        $teams = collect(['General Medicine', 'General Surgery', 'Cardiology', 'Respiratory', 'Orthopaedics', 'Paediatrics', 'Obstetrics', 'Critical Care'])
            ->mapWithKeys(fn ($n) => [$n => Team::create(['name' => $n, 'specialty' => $n])]);

        $wardDefs = [
            ['Medical Ward A', 'MWA', 'General Medicine', WardType::General, RolloutStage::Pilot, 18],
            ['Surgical Ward', 'SWB', 'General Surgery', WardType::General, RolloutStage::Live, 18],
            ['Paediatric Ward', 'PAED', 'Paediatrics', WardType::General, RolloutStage::Live, 12],
            ['Intensive Care Unit', 'ICU', 'Critical Care', WardType::CriticalCare, RolloutStage::Pilot, 8],
            ['High Dependency Unit', 'HDU', 'Critical Care', WardType::CriticalCare, RolloutStage::Live, 6],
            ['Accident & Emergency', 'AE', 'Emergency Medicine', WardType::Emergency, RolloutStage::Live, 16],
        ];
        $wards = [];
        foreach ($wardDefs as [$name, $code, $spec, $type, $stage, $beds]) {
            $ward = Ward::create(['name' => $name, 'code' => $code, 'specialty' => $spec]);
            $config = $configs->createDefault($ward, $type);
            $config->update(['rollout_stage' => $stage, 'bed_count' => $beds]);
            app(BedManager::class)->sync($ward, $config->refresh());
            $wards[$code] = $ward->refresh();
        }
        // The paediatric ward keeps obs 4-hourly but has no Procedure tab: a settings-only difference.
        $wards['PAED']->config->update(['enabled_tabs' => ['history', 'investigations', 'medication', 'observations']]);

        $admin = User::create(['name' => 'Ward Systems Admin', 'email' => 'admin@hospital.test', 'password' => $password, 'role' => UserRole::Admin]);
        $staff = [
            ['Dr. Chidi Okafor', 'dr.okafor', UserRole::Doctor, 'MWA', 'General Medicine'],
            ['Dr. Halima Sani', 'dr.sani', UserRole::Doctor, 'SWB', 'General Surgery'],
            ['Nurse Amina Bello', 'nurse.bello', UserRole::Nurse, 'MWA', null],
            ['Nurse Grace Obi', 'nurse.obi', UserRole::Nurse, 'MWA', null],
            ['Nurse Fatima Lawal', 'nurse.lawal', UserRole::Nurse, 'SWB', null],
            ['Dr. Tunde Adeyemi', 'dr.adeyemi', UserRole::Doctor, 'ICU', 'Critical Care'],
            ['Nurse Musa Yusuf', 'nurse.yusuf', UserRole::Nurse, 'ICU', null],
            ['Nurse Joy Akpan', 'nurse.akpan', UserRole::Nurse, 'ICU', null],
            ['Dr. Ngozi Eze', 'dr.eze', UserRole::Doctor, 'AE', 'General Medicine'],
            ['Nurse Ibrahim Danjuma', 'nurse.danjuma', UserRole::Nurse, 'AE', null],
        ];
        $users = [];
        foreach ($staff as [$name, $login, $role, $ward, $team]) {
            $users[$login] = User::create([
                'name' => $name, 'email' => "{$login}@hospital.test", 'password' => $password, 'role' => $role,
                'ward_id' => $wards[$ward]->id, 'team_id' => $team ? $teams[$team]->id : null,
            ]);
        }

        $patient = function (string $name, string $dob, string $sex, array $extra = []) {
            static $n = 100230;

            return Patient::create(array_merge(['mrn' => 'H'.(++$n), 'name' => $name, 'date_of_birth' => $dob, 'sex' => $sex], $extra));
        };
        $admit = function (Patient $p, Ward $ward, int $bedPos, string $team, array $dx, int $hoursAgo, array $extra = []) use ($teams) {
            return $p->encounters()->create(array_merge([
                'ward_id' => $ward->id, 'bed_id' => $ward->beds()->where('position', $bedPos)->value('id'),
                'team_id' => $teams[$team]->id, 'status' => EncounterStatus::Admitted, 'diagnoses' => $dx,
                'arrived_at' => now()->subHours($hoursAgo + 3), 'admitted_at' => now()->subHours($hoursAgo),
            ], $extra));
        };
        $entry = fn (Encounter $e, User $u, EntryCategory $cat, string $key, string $summary, array $payload = [], array $extra = []) => $e->entries()->create(array_merge([
            'user_id' => $u->id, 'category' => $cat, 'action_key' => $key, 'summary' => $summary, 'payload' => $payload, 'recorded_at' => now(),
        ], $extra));
        $obs = function (Encounter $e, User $u, array $v, int $minutesAgo) use ($news, $entry) {
            $r = $news->calculate($v);
            $entry($e, $u, EntryCategory::Observation, 'record_observations',
                "NEWS2 {$r['score']} — RR {$v['respiratory_rate']}, SpO₂ {$v['spo2']}%, BP {$v['systolic_bp']}, HR {$v['heart_rate']}, T {$v['temperature']}°",
                $v + ['news_parts' => $r['parts'], 'band' => $r['band']],
                ['score' => $r['score'], 'status' => $r['band'], 'recorded_at' => now()->subMinutes($minutesAgo)]);
        };
        $vitals = fn ($rr, $spo2, $o2, $sbp, $dbp, $hr, $t, $c = 'A', $extra = []) => array_merge([
            'respiratory_rate' => $rr, 'spo2' => $spo2, 'on_oxygen' => $o2, 'systolic_bp' => $sbp, 'diastolic_bp' => $dbp,
            'heart_rate' => $hr, 'temperature' => $t, 'consciousness' => $c,
        ], $extra);

        $drO = $users['dr.okafor'];
        $nB = $users['nurse.bello'];
        $mwa = $wards['MWA'];

        // --- Medical Ward A (pilot general ward) ---
        $e1 = $admit($patient('Abubakar Musa', '1958-03-14', 'male', ['allergies' => ['Penicillin (rash)'], 'dnr_status' => DnrStatus::FullEscalation]), $mwa, 1, 'General Medicine', ['Community-acquired pneumonia', 'Type 2 diabetes'], 30);
        $entry($e1, $drO, EntryCategory::Note, 'ward_round_note', 'Ward round: Day 2 CAP. Improving, eating. Chest clearer on the right.', ['progress' => 'Day 2 CAP. Improving, eating.', 'plan' => 'Continue IV antibiotics 48h then switch to oral.'], ['type' => 'ward_round', 'recorded_at' => now()->subHours(5)]);
        $rx = $entry($e1, $drO, EntryCategory::Medication, 'prescribe', 'Ceftriaxone 2 g IV OD', ['drug' => 'Ceftriaxone', 'dose' => '2 g', 'route' => 'IV', 'frequency' => 'OD', 'indication' => 'CAP'], ['type' => 'new', 'status' => 'active', 'recorded_at' => now()->subHours(28)]);
        $entry($e1, $nB, EntryCategory::Administration, 'administer_medication', 'Given: Ceftriaxone 2 g', ['drug' => 'Ceftriaxone', 'dose' => '2 g'], ['type' => 'given', 'status' => 'given', 'parent_id' => $rx->id, 'recorded_at' => now()->subHours(4)]);
        $entry($e1, $drO, EntryCategory::Medication, 'prescribe', 'Metformin 500 mg PO BD', ['drug' => 'Metformin', 'dose' => '500 mg', 'route' => 'PO', 'frequency' => 'BD'], ['type' => 'new', 'status' => 'active', 'recorded_at' => now()->subHours(28)]);
        $entry($e1, $drO, EntryCategory::Investigation, 'request', 'FBC (Routine)', ['item' => 'FBC', 'urgency' => 'routine', 'clinical_details' => 'Monitor WCC', 'kind' => 'Laboratory'], ['type' => 'lab', 'status' => 'requested', 'recorded_at' => now()->subHours(2)]);
        $entry($e1, $drO, EntryCategory::Investigation, 'request', 'CRP (Routine)', ['item' => 'CRP', 'urgency' => 'routine', 'clinical_details' => 'Monitor response', 'kind' => 'Laboratory'], ['type' => 'lab', 'status' => 'requested', 'recorded_at' => now()->subHours(2)]);
        $entry($e1, $drO, EntryCategory::Investigation, 'request', 'Radiology / scan: Chest X-ray (Routine)', ['item' => 'Chest X-ray', 'urgency' => 'routine', 'clinical_details' => 'RLL consolidation', 'kind' => 'Radiology / scan', 'result' => 'Right lower lobe consolidation', 'abnormal' => true, 'resulted_by' => 'Dr. Radiologist'], ['type' => 'radiology', 'status' => 'resulted', 'recorded_at' => now()->subHours(29)]);
        foreach ([[600, 22, 93, true, 102, 62, 104, 38.4], [360, 20, 94, true, 110, 70, 96, 37.9], [120, 18, 95, false, 118, 74, 88, 37.4]] as [$m, $rr, $s, $o, $sb, $db, $hr, $t]) {
            $obs($e1, $nB, $vitals($rr, $s, $o, $sb, $db, $hr, $t, 'A', ['pain_score' => 2, 'fluid_in_ml' => 400, 'fluid_out_ml' => 300]), $m);
        }

        $e2 = $admit($patient('Hauwa Ibrahim', '1941-11-02', 'female', ['allergies' => ['NKDA'], 'dnr_status' => DnrStatus::Dnacpr]), $mwa, 2, 'General Medicine', ['Decompensated heart failure', 'AKI stage 1'], 50);
        $obs($e2, $nB, $vitals(26, 91, true, 98, 60, 118, 37.0, 'C'), 290);
        $entry($e2, $nB, EntryCategory::Alert, 'alert_doctor', 'Deteriorating patient (Urgent): Increasing breathlessness, new confusion, NEWS2 11', ['urgency' => 'urgent', 'situation' => 'Increasing breathlessness, new confusion', 'recommendation' => 'Review within 30 minutes'], ['type' => 'deteriorating', 'status' => 'open', 'recorded_at' => now()->subMinutes(280)]);
        $entry($e2, $drO, EntryCategory::Medication, 'prescribe', 'Furosemide 80 mg IV BD', ['drug' => 'Furosemide', 'dose' => '80 mg', 'route' => 'IV', 'frequency' => 'BD', 'indication' => 'Fluid overload'], ['type' => 'new', 'status' => 'active', 'recorded_at' => now()->subHours(40)]);

        $e3 = $admit($patient('Chinedu Nwosu', '1975-06-21', 'male'), $mwa, 4, 'Respiratory', ['Asthma exacerbation'], 20);
        $obs($e3, $nB, $vitals(18, 96, false, 128, 80, 84, 36.8), 100);
        $e3->riskAssessments()->createMany([
            ['kind' => 'falls', 'score' => 15, 'risk_level' => 'low', 'user_id' => $nB->id, 'completed_at' => now()->subHours(19), 'next_due_at' => now()->addHours(5)],
            ['kind' => 'pressure', 'score' => 6, 'risk_level' => 'low', 'user_id' => $nB->id, 'completed_at' => now()->subHours(19), 'next_due_at' => now()->addHours(5)],
            ['kind' => 'nutrition', 'score' => 0, 'risk_level' => 'low', 'user_id' => $nB->id, 'completed_at' => now()->subHours(19), 'next_due_at' => now()->addDays(6)],
        ]);
        $entry($e3, $drO, EntryCategory::Discharge, 'discharge', 'Discharge home started', ['discharge_type' => 'Home', 'summary' => 'Asthma exacerbation, now peak flow 85% predicted.', 'follow_up' => 'Asthma clinic 2 weeks'], ['type' => 'home', 'status' => 'started', 'recorded_at' => now()->subHour()]);
        $e3->update([
            'status' => EncounterStatus::DischargePending, 'discharge_type' => 'home', 'discharge_started_at' => now()->subHour(),
            'discharge_checklist' => collect(config('ward.discharge_checklists.default'))->map(fn ($l, $k) => ['label' => $l, 'done' => in_array($k, ['meds_reconciled', 'cannula_removed']), 'by' => in_array($k, ['meds_reconciled', 'cannula_removed']) ? $nB->name : null, 'at' => in_array($k, ['meds_reconciled', 'cannula_removed']) ? now()->format('H:i j M') : null])->all(),
        ]);

        $e4 = $admit($patient('Blessing Eze', '1990-01-09', 'female', ['allergies' => ['Sulfonamides']]), $mwa, 7, 'General Medicine', ['Severe malaria'], 10);
        $obs($e4, $users['nurse.obi'], $vitals(20, 97, false, 112, 70, 102, 38.6), 200);
        $entry($e4, $drO, EntryCategory::Medication, 'prescribe', 'Artesunate 2.4 mg/kg IV STAT', ['drug' => 'Artesunate', 'dose' => '2.4 mg/kg', 'route' => 'IV', 'frequency' => 'STAT'], ['type' => 'new', 'status' => 'active', 'recorded_at' => now()->subHours(9)]);
        $entry($e4, $drO, EntryCategory::Movement, 'move_patient', 'Left ward for Radiology / scan', ['destination' => 'Radiology / scan'], ['type' => 'off_ward', 'recorded_at' => now()->subMinutes(20)]);
        $e4->update(['off_ward' => 'Radiology / scan']);

        $mwa->tasks()->create(['encounter_id' => $e1->id, 'title' => 'Encourage oral fluids; repeat capillary glucose before meals', 'for_role' => 'nurse', 'created_by' => $drO->id]);
        $mwa->tasks()->create(['encounter_id' => $e2->id, 'title' => 'Chase echo report', 'for_role' => 'doctor', 'created_by' => $drO->id, 'due_at' => now()->addHours(3)]);
        $mwa->handoverNotes()->create(['user_id' => $nB->id, 'role' => 'nurse', 'shift' => 'night', 'body' => "B1 Abubakar Musa — CAP, improving, obs 4-hourly.\nB2 Hauwa Ibrahim — DNACPR, NEWS2 high overnight, doctor alerted.\nB4 Chinedu Nwosu — going home today, checklist half done."]);

        // --- Surgical ward ---
        $s1 = $admit($patient('Emeka Obi', '1968-08-30', 'male', ['allergies' => ['NKDA']]), $wards['SWB'], 3, 'General Surgery', ['Acute appendicitis'], 6);
        $entry($s1, $users['dr.sani'], EntryCategory::Procedure, 'procedure', 'Theatre booked: Laparoscopic appendicectomy on '.now()->format('j M'), ['procedure' => 'Laparoscopic appendicectomy', 'urgency' => 'urgent', 'indication' => 'Acute appendicitis', 'consent' => 'Consent obtained', 'anaesthetic' => 'general'], ['type' => 'theatre', 'status' => 'booked', 'recorded_at' => now()->subHours(5)]);
        $obs($s1, $users['nurse.lawal'], $vitals(18, 98, false, 130, 82, 92, 37.8), 90);

        // --- ICU (pilot critical care) ---
        $icu = $wards['ICU'];
        $drA = $users['dr.adeyemi'];
        $nY = $users['nurse.yusuf'];
        $i1 = $admit($patient('Yakubu Garba', '1962-04-17', 'male', ['critical_flag' => true, 'critical_reason' => 'Septic shock on noradrenaline']), $icu, 1, 'Critical Care', ['Septic shock', 'Ventilator-dependent respiratory failure'], 40, ['assigned_nurse_id' => $nY->id, 'obs_frequency_hours' => 1]);
        $nor = $entry($i1, $drA, EntryCategory::Medication, 'prescribe', 'Noradrenaline 0.1 mcg/kg/min IV CONT', ['drug' => 'Noradrenaline', 'dose' => '0.1 mcg/kg/min', 'route' => 'IV', 'frequency' => 'CONT', 'infusion' => true], ['type' => 'new', 'status' => 'active', 'recorded_at' => now()->subHours(36)]);
        $prop = $entry($i1, $drA, EntryCategory::Medication, 'prescribe', 'Propofol 1% 2 mg/kg/hr IV CONT', ['drug' => 'Propofol', 'dose' => '2 mg/kg/hr', 'route' => 'IV', 'frequency' => 'CONT', 'infusion' => true], ['type' => 'new', 'status' => 'active', 'recorded_at' => now()->subHours(36)]);
        for ($h = 6; $h >= 1; $h--) {
            $obs($i1, $nY, $vitals(16, 94 + ($h % 2), true, 96 + (6 - $h) * 3, 55, 112 - (6 - $h) * 2, 38.1, 'U', [
                'fluid_in_ml' => 120, 'fluid_out_ml' => 40 + $h * 5,
                'ventilator' => ['mode' => 'SIMV', 'mode_key' => 'simv', 'fio2' => 40 + ($h > 3 ? 10 : 0), 'peep' => 8, 'tidal_volume' => 450, 'set_rate' => 16],
                'infusions' => [['prescription_id' => $nor->id, 'drug' => 'Noradrenaline', 'rate' => (8 - $h * 0.5).' ml/hr'], ['prescription_id' => $prop->id, 'drug' => 'Propofol', 'rate' => '12 ml/hr']],
            ]), $h * 60 + 10);
        }
        $i2 = $admit($patient('Aisha Suleiman', '1985-12-05', 'female', ['allergies' => ['NKDA']]), $icu, 3, 'Critical Care', ['Post-op day 1 Whipple'], 20, ['assigned_nurse_id' => $users['nurse.akpan']->id]);
        $obs($i2, $users['nurse.akpan'], $vitals(18, 97, true, 124, 72, 88, 37.2, 'A', ['ventilator' => ['mode' => 'High-flow nasal oxygen', 'mode_key' => 'hfno', 'fio2' => 35]]), 50);

        // --- A&E ---
        $ae = $wards['AE'];
        $aeCases = [
            ['Ikenna Uche', '1980-02-11', 'male', 'Central chest pain, sweaty', TriageCategory::VeryUrgent, 25, null],
            ['Zainab Aliyu', '2001-07-19', 'female', 'Abdominal pain, vomiting', TriageCategory::Urgent, 95, null],
            ['Peter Okon', '1995-10-03', 'male', 'Laceration to left hand', TriageCategory::Standard, 150, null],
            ['Mary Johnson', '1950-05-28', 'female', 'Fall at home, hip pain', TriageCategory::Urgent, 210, 60],
            ['Sani Bala', '2010-09-14', 'male', 'Fever and rash', null, 8, null],
        ];
        foreach ($aeCases as [$name, $dob, $sex, $pc, $cat, $minsAgo, $seenAfter]) {
            $e = $patient($name, $dob, $sex)->encounters()->create([
                'ward_id' => $ae->id, 'status' => EncounterStatus::Waiting, 'presenting_complaint' => $pc,
                'arrived_at' => now()->subMinutes($minsAgo), 'triage_category' => $cat,
                'triaged_at' => $cat ? now()->subMinutes($minsAgo - 5) : null,
                'seen_at' => $seenAfter ? now()->subMinutes($minsAgo - $seenAfter) : null,
            ]);
            if ($cat) {
                $entry($e, $users['nurse.danjuma'], EntryCategory::Triage, 'triage', 'Triaged '.$cat->label().": {$pc}", ['category' => $cat->value, 'presenting_complaint' => $pc], ['type' => 'triage', 'recorded_at' => now()->subMinutes($minsAgo - 5)]);
            }
        }
    }
}
