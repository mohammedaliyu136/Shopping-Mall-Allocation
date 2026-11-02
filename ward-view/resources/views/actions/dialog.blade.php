{{-- Shared shell for every action button form. --}}
@php($types = $action->typesFor($encounter))
<form method="POST" action="{{ route('actions.store', [$encounter, $action->key()]) }}" data-typed>
    @csrf
    <div class="dlg-head">
        <div class="grow">
            <h2>{{ $action->icon() }} {{ $action->label() }}</h2>
            <small>{{ $encounter->patient->name }} · {{ $encounter->bed ? 'Bed '.$encounter->bed->label.' · ' : '' }}{{ $encounter->ward->name }}</small>
        </div>
        <button class="link" type="button" data-close>Close</button>
    </div>
    <div class="dlg-body">
        @if ($errors->any())
            <div class="flash err">Please check the highlighted fields.</div>
        @endif
        @if ($unavailable)
            <p class="hint warn">{{ $unavailable }}</p>
        @else
            @if ($types)
                <label>Type</label>
                <div class="type-selector" role="radiogroup">
                    @foreach ($types as $value => $label)
                        <label><input type="radio" name="type" value="{{ $value }}" @checked(old('type', array_key_first($types)) === $value)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                @include('partials.field-error', ['name' => 'type'])
            @endif
            @include($action->view())
        @endif
    </div>
    <div class="dlg-foot">
        <button class="btn secondary" type="button" data-close>Cancel</button>
        @unless ($unavailable)<button class="btn" type="submit">Save</button>@endunless
    </div>
</form>
