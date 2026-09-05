<div class="letterhead">
    <table><tr>
        <td>
            <div class="mall">{{ $mall?->name ?? config('app.name') }}</div>
            <div class="meta">
                {{ $mall?->address }}<br>
                @if ($mall?->phone) Tel: {{ $mall->phone }} @endif
                @if ($mall?->email) &middot; {{ $mall->email }} @endif
            </div>
        </td>
        <td class="right meta" style="width:34%">
            <strong>{{ $documentLabel ?? '' }}</strong><br>
            @if (! empty($documentNumber)) <span class="mono">{{ $documentNumber }}</span><br> @endif
            Issued: {{ ($issuedAt ?? now())->toFormattedDateString() }}
        </td>
    </tr></table>
</div>
