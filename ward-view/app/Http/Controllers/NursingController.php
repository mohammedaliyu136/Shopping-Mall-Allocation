<?php

namespace App\Http\Controllers;

use App\Enums\EntryCategory;
use App\Models\Encounter;
use App\Services\PatientMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Nurse work that lives outside the button bar: risk assessments and the discharge checklist. */
class NursingController extends Controller
{
    public function riskAssessment(Request $request, Encounter $encounter, string $kind)
    {
        abort_unless($request->user()->isNurse(), 403);
        $def = config("ward.risk_assessments.{$kind}") ?? abort(404);

        $data = $request->validate([
            'score' => ['required', 'integer', 'between:0,'.$def['max']],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $score = (int) $data['score'];
        $level = $score >= $def['high_at'] ? 'high' : ($score >= $def['medium_at'] ? 'medium' : 'low');
        // Higher-risk patients are reassessed sooner.
        $hours = $level === 'high' ? min(24, $def['every_hours']) : $def['every_hours'];

        $encounter->riskAssessments()->create([
            'kind' => $kind, 'score' => $score, 'risk_level' => $level, 'notes' => $data['notes'] ?? null,
            'user_id' => $request->user()->id, 'completed_at' => now(), 'next_due_at' => now()->addHours($hours),
        ]);
        $encounter->entries()->create([
            'user_id' => $request->user()->id, 'category' => EntryCategory::Assessment, 'action_key' => 'risk_assessment',
            'type' => $kind, 'summary' => "{$def['label']} risk assessment ({$def['tool']}): score {$score}, {$level} risk",
            'payload' => ['tool' => $def['tool'], 'notes' => $data['notes'] ?? null], 'score' => $score, 'status' => $level, 'recorded_at' => now(),
        ]);

        return back()->with('status', "{$def['label']} assessment saved: {$level} risk.");
    }

    /** The discharge checklist appears only once the doctor has started a discharge. */
    public function dischargeChecklist(Request $request, Encounter $encounter, PatientMovement $movement)
    {
        abort_unless($request->user()->isNurse() && $encounter->isDischargePending(), 403);
        $checklist = $encounter->discharge_checklist ?? [];
        $data = $request->validate(['items' => ['nullable', 'array'], 'items.*' => [Rule::in(array_keys($checklist))]]);
        $ticked = $data['items'] ?? [];

        foreach ($checklist as $key => $item) {
            $done = in_array($key, $ticked, true);
            if ($done && ! $item['done']) {
                $checklist[$key] = ['label' => $item['label'], 'done' => true, 'by' => $request->user()->name, 'at' => now()->format('H:i j M')];
            } elseif (! $done) {
                $checklist[$key] = ['label' => $item['label'], 'done' => false, 'by' => null, 'at' => null];
            }
        }
        $encounter->update(['discharge_checklist' => $checklist]);

        $complete = collect($checklist)->every(fn ($i) => $i['done']);
        if ($complete && $request->boolean('complete')) {
            $ward = $encounter->ward;
            $encounter->entries()->create([
                'user_id' => $request->user()->id, 'category' => EntryCategory::Discharge, 'action_key' => 'discharge_checklist',
                'type' => $encounter->discharge_type, 'summary' => 'Discharge checklist complete; patient has left the ward',
                'payload' => ['checklist' => $checklist], 'status' => 'completed', 'recorded_at' => now(),
            ]);
            $movement->close($encounter, $encounter->discharge_type);

            return redirect()->route('wards.show', $ward)->with('status', $encounter->patient->name.' discharged. Bed is now free.');
        }

        return back()->with('status', $complete ? 'Checklist complete. Press "Complete discharge" to free the bed.' : 'Checklist saved.');
    }
}
