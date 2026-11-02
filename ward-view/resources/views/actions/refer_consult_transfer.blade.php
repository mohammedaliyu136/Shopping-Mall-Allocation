@if ($pending)
    <p class="hint warn">A transfer to {{ $pending->toWard?->name }} is already pending. Cancel it from the patient header before requesting another.</p>
@endif
<div data-types="consult">
    <p class="hint"><strong>Consult only:</strong> another team gives advice. The patient <strong>stays under your team</strong> and on this ward.</p>
    <label>Team to consult</label>
    <select name="team_id">@foreach ($teams as $t)@continue($t->id === $encounter->team_id)<option value="{{ $t->id }}" @selected((string) old('team_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach</select>
    <label>Question for the team</label><textarea name="question">{{ old('question') }}</textarea>
    @include('partials.field-error', ['name' => 'question'])
</div>
<div data-types="transfer_team">
    <p class="hint"><strong>Transfer to team:</strong> ownership moves to the new team <strong>now</strong>. The patient stays in the same bed.</p>
    <label>New team</label>
    <select name="team_id">@foreach ($teams as $t)@continue($t->id === $encounter->team_id)<option value="{{ $t->id }}" @selected((string) old('team_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach</select>
</div>
<div data-types="transfer_ward">
    <p class="hint"><strong>Transfer to ward:</strong> the patient moves when a nurse on the receiving ward accepts them.</p>
    <label>Receiving ward</label>
    <select name="to_ward_id">@foreach ($generalWards as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
    <label>Receiving team (optional)</label>
    <select name="to_team_id"><option value="">Keep current team</option>@foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
</div>
<div data-types="escalate">
    <p class="hint warn"><strong>Escalate to ICU / HDU:</strong> flags the patient as critical and requests a critical care bed.</p>
    <label>Critical care unit</label>
    <select name="to_ward_id">@foreach ($criticalWards as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
</div>
@include('partials.field-error', ['name' => 'team_id'])
@include('partials.field-error', ['name' => 'to_ward_id'])
<div data-types="transfer_team transfer_ward escalate">
    <label>Reason</label><textarea name="reason">{{ old('reason') }}</textarea>
    @include('partials.field-error', ['name' => 'reason'])
</div>
<label>Urgency</label>
<select name="urgency">@foreach (config('ward.urgencies') as $k => $l)<option value="{{ $k }}" @selected(old('urgency') === $k)>{{ $l }}</option>@endforeach</select>
