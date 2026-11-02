@php($p = $encounter->patient)
@if (auth()->user()->isDoctor())
<dialog id="edit-resus">
    <form method="POST" action="{{ route('encounters.header', [$encounter, 'resus']) }}">@csrf
        <div class="dlg-head"><h2 class="grow">Resuscitation status</h2><button class="link" type="button" data-close>Close</button></div>
        <div class="dlg-body">
            @foreach (\App\Enums\DnrStatus::options() as $v => $l)
                <label class="inline"><input type="radio" name="dnr_status" value="{{ $v }}" @checked($p->dnr_status->value === $v)> {{ $l }}</label><br>
            @endforeach
            <label>Discussion / reason</label><textarea name="reason" placeholder="Who was involved in the decision"></textarea>
        </div>
        <div class="dlg-foot"><button class="btn" type="submit">Save</button></div>
    </form>
</dialog>
<dialog id="edit-critical">
    <form method="POST" action="{{ route('encounters.header', [$encounter, 'critical']) }}" data-typed>@csrf
        <div class="dlg-head"><h2 class="grow">Critical flag</h2><button class="link" type="button" data-close>Close</button></div>
        <div class="dlg-body">
            <label class="inline"><input type="radio" name="critical_flag" value="1" @checked($p->critical_flag)> Flag as critical</label>
            <label class="inline"><input type="radio" name="critical_flag" value="0" @checked(! $p->critical_flag)> Not critical</label>
            <div data-show-when="critical_flag=1"><label>Reason</label><input type="text" name="critical_reason" value="{{ $p->critical_reason }}"></div>
        </div>
        <div class="dlg-foot"><button class="btn" type="submit">Save</button></div>
    </form>
</dialog>
@endif
@if (auth()->user()->isClinical())
<dialog id="edit-allergies">
    <form method="POST" action="{{ route('encounters.header', [$encounter, 'allergies']) }}">@csrf
        <div class="dlg-head"><h2 class="grow">Allergies</h2><button class="link" type="button" data-close>Close</button></div>
        <div class="dlg-body">
            <label>Allergies (comma separated)</label>
            <input type="text" name="allergies" value="{{ $p->hasAllergies() ? implode(', ', $p->allergies) : '' }}" placeholder="e.g. Penicillin (rash), Latex">
            <label class="inline"><input type="checkbox" name="nkda" value="1" @checked($p->allergies === ['NKDA'])> No known drug allergies</label>
        </div>
        <div class="dlg-foot"><button class="btn" type="submit">Save</button></div>
    </form>
</dialog>
@endif
@if (auth()->user()->isNurse())
    @foreach ($riskDefs as $kind => $def)
        <dialog id="edit-risk-{{ $kind }}">
            <form method="POST" action="{{ route('encounters.risk', [$encounter, $kind]) }}">@csrf
                <div class="dlg-head"><div class="grow"><h2>{{ $def['label'] }} risk assessment</h2><small>{{ $def['tool'] }} · medium risk from {{ $def['medium_at'] }}, high from {{ $def['high_at'] }}</small></div><button class="link" type="button" data-close>Close</button></div>
                <div class="dlg-body">
                    <label>Score (0–{{ $def['max'] }})</label><input type="number" name="score" min="0" max="{{ $def['max'] }}" required>
                    <label>Actions taken / notes</label><textarea name="notes"></textarea>
                </div>
                <div class="dlg-foot"><button class="btn" type="submit">Save assessment</button></div>
            </form>
        </dialog>
    @endforeach
@endif
