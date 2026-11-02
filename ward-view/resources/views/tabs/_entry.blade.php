{{-- One signed entry. Sign / co-sign is inline here, not a global button. --}}
@php($user = auth()->user())
<article class="entry">
    <div class="when">
        {{ $entry->recorded_at->format('j M H:i') }}<br>
        <span class="pill {{ $entry->category === \App\Enums\EntryCategory::Alert ? 'red' : ($entry->category === \App\Enums\EntryCategory::Discharge ? 'green' : '') }}">{{ $entry->category->label() }}</span>
    </div>
    <div>
        <div>{{ $entry->summary }}
            @if ($entry->status && ! in_array($entry->category, [\App\Enums\EntryCategory::Observation, \App\Enums\EntryCategory::Note]))
                <span class="pill {{ in_array($entry->status, ['open', 'requested', 'pending']) ? 'amber' : (in_array($entry->status, ['stopped', 'cancelled']) ? '' : 'green') }}">{{ str_replace('_', ' ', $entry->status) }}</span>
            @endif
        </div>
        @php($details = collect($entry->payload ?? [])->except(['news_parts', 'band', 'mode_key', 'checklist', 'transfer_id', 'infusion'])->filter(fn ($v) => is_scalar($v) && $v !== '' && ! is_bool($v)))
        @if ($details->isNotEmpty() && ($full ?? true))
            <dl class="details">
                @foreach ($details as $k => $v)
                    <dt>{{ ucfirst(str_replace('_', ' ', $k)) }}:</dt><dd>{{ \Illuminate\Support\Str::limit((string) $v, 300) }}</dd>
                @endforeach
            </dl>
        @endif
        @if ($entry->category === \App\Enums\EntryCategory::Alert && $entry->status === 'open' && $user->isDoctor())
            <form method="POST" action="{{ route('entries.acknowledge', $entry) }}" class="row" style="margin-top:6px;gap:6px">@csrf
                <input type="text" name="response" placeholder="Response, e.g. Coming to review in 15 min" style="flex:1;padding:4px 8px">
                <button class="btn sm" type="submit">Acknowledge</button>
            </form>
        @endif
    </div>
    <div class="sign">
        Signed {{ $entry->author->name }}<br>
        @if ($entry->cosigned_at)
            Co-signed {{ $entry->cosigner->name }} {{ $entry->cosigned_at->format('H:i') }}
        @elseif ($entry->canBeCosignedBy($user))
            <form method="POST" action="{{ route('entries.cosign', $entry) }}">@csrf <button class="link" type="submit">Co-sign</button></form>
        @endif
    </div>
</article>
