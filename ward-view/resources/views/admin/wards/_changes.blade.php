<table>
    <tr><th>When</th><th>Who</th><th>Version</th><th>What changed</th></tr>
    @foreach ($changes as $c)
        <tr>
            <td><small>{{ $c->created_at->format('j M Y H:i') }}</small></td>
            <td>{{ $c->user_name }}</td>
            <td>v{{ $c->version }}</td>
            <td>
                @if ($c->event === 'created')
                    Created with {{ \App\Enums\WardType::from($c->after['ward_type'])->label() }} defaults
                @else
                    @foreach ($c->diff() as $field => [$old, $new])
                        <div><strong>{{ str_replace('_', ' ', $field) }}:</strong>
                            @if (is_array($new))
                                @php($added = array_diff($new, $old ?? []))
                                @php($removed = array_diff($old ?? [], $new))
                                @if ($added)<span class="pill green">+ {{ implode(', ', $added) }}</span>@endif
                                @if ($removed)<span class="pill red">− {{ implode(', ', $removed) }}</span>@endif
                            @else
                                {{ $old ?? '—' }} → {{ $new }}
                            @endif
                        </div>
                    @endforeach
                @endif
            </td>
        </tr>
    @endforeach
</table>
