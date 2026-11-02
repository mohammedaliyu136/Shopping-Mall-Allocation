<div class="filters">
    <a href="{{ route('encounters.tab', [$encounter, 'history']) }}" data-inplace class="{{ ! $filter ? 'active' : '' }}">All</a>
    @foreach ($categories as $c)
        @php($cat = $c instanceof \App\Enums\EntryCategory ? $c : \App\Enums\EntryCategory::from($c))
        <a href="{{ route('encounters.tab', [$encounter, 'history']) }}?category={{ $cat->value }}" data-inplace class="{{ $filter === $cat->value ? 'active' : '' }}">{{ $cat->label() }}</a>
    @endforeach
</div>
@forelse ($entries as $entry)
    @include('tabs._entry')
@empty
    <p class="muted">Nothing recorded yet.</p>
@endforelse
