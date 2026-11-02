<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\Ward;
use App\Models\WardTask;
use Illuminate\Http\Request;

/** Handover and tasks are ward-level: staff hand over the whole patient list. */
class HandoverController extends Controller
{
    public function index(Request $request, Ward $ward)
    {
        $role = $request->user()->role->value;

        return view('handover.index', [
            'ward' => $ward,
            'notes' => $ward->handoverNotes()->with('author')->where('created_at', '>=', now()->subDays(2))->get(),
            'tasks' => $ward->tasks()->with(['encounter.patient', 'encounter.bed', 'creator', 'completer'])
                ->where(fn ($q) => $q->whereNull('done_at')->orWhere('done_at', '>=', now()->subDay()))
                ->orderByRaw('done_at is not null')->orderBy('due_at')->get()
                ->groupBy('for_role'),
            'role' => $role,
            'encounters' => $ward->activeEncounters()->with(['patient', 'bed'])->get(),
        ]);
    }

    public function storeNote(Request $request, Ward $ward)
    {
        $data = $request->validate(['shift' => ['required', 'in:day,evening,night'], 'body' => ['required', 'string', 'max:10000']]);
        $ward->handoverNotes()->create($data + ['user_id' => $request->user()->id, 'role' => $request->user()->role->value]);

        return back()->with('status', 'Handover saved.');
    }

    public function storeTask(Request $request, Ward $ward)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'for_role' => ['required', 'in:doctor,nurse'],
            'encounter_id' => ['nullable', 'exists:encounters,id'],
            'due_at' => ['nullable', 'date'],
        ]);
        if (! empty($data['encounter_id'])) {
            abort_unless(Encounter::find($data['encounter_id'])->ward_id === $ward->id, 422);
        }
        $ward->tasks()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Task added.');
    }

    public function completeTask(Request $request, Ward $ward, WardTask $task)
    {
        abort_unless($task->ward_id === $ward->id, 404);
        $task->update($task->done_at ? ['done_at' => null, 'done_by' => null] : ['done_at' => now(), 'done_by' => $request->user()->id]);

        return back();
    }
}
