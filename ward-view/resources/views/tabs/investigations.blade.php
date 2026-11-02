@forelse ($groups as $kind => $items)
    <h3 style="margin-top:8px">{{ $kind }}</h3>
    <table>
        <tr><th>Requested</th><th>Item</th><th>Urgency</th><th>Status</th><th>Result</th></tr>
        @foreach ($items as $i)
            <tr>
                <td><small>{{ $i->recorded_at->format('j M H:i') }}<br>{{ $i->author->name }}</small></td>
                <td>{{ $i->data('item') }}<br><small class="muted">{{ $i->data('clinical_details') }}</small></td>
                <td>{{ config('ward.urgencies')[$i->data('urgency')] ?? '—' }}</td>
                <td><span class="pill {{ ['requested' => 'amber', 'collected' => 'blue', 'resulted' => 'green'][$i->status] ?? '' }}">{{ $i->status }}</span>
                    @if ($i->data('collected_at'))<br><small>taken {{ \Illuminate\Support\Carbon::parse($i->data('collected_at'))->format('H:i j M') }}</small>@endif</td>
                <td>
                    @if ($i->status === 'resulted')
                        <span class="{{ $i->data('abnormal') ? 'pill red' : '' }}">{{ $i->data('result') }}</span><br><small class="muted">{{ $i->data('resulted_by') }}</small>
                    @elseif (auth()->user()->isClinical())
                        <form method="POST" action="{{ route('entries.result', $i) }}" class="row" style="gap:4px">@csrf
                            <input type="text" name="result" placeholder="Enter result" style="flex:1;padding:4px 8px" required>
                            <label class="inline" style="margin:0"><input type="checkbox" name="abnormal" value="1"> Abnormal</label>
                            <button class="btn sm" type="submit">Save</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@empty
    <p class="muted">No investigations requested.</p>
@endforelse
