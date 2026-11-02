@forelse ($procedures as $entry)
    @include('tabs._entry')
@empty
    <p class="muted">No procedures ordered.</p>
@endforelse
