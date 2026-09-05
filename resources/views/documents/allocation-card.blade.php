{{-- SRS FR-070 - the allocation card, sized to an ID-1 card. --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Allocation Card {{ $card->card_number }}</title>
<style>
    @page { margin: 6px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 7.5pt; color: #1c2530; margin: 0; }
    .card { border: 1px solid #0f4c81; padding: 7px; height: 132px; }
    .head { background: #0f4c81; color: #fff; padding: 4px 6px; margin: -7px -7px 6px; }
    .head .mall { font-size: 9pt; font-weight: bold; }
    .head .kind { font-size: 6.5pt; letter-spacing: .09em; text-transform: uppercase; }
    td { vertical-align: top; padding: 0; }
    .k { color: #5b6875; font-size: 6.5pt; text-transform: uppercase; letter-spacing: .04em; }
    .v { font-size: 8pt; font-weight: bold; margin-bottom: 3px; }
    .mono { font-family: "DejaVu Sans Mono", monospace; }
    .photo { width: 46px; height: 56px; border: 1px solid #dde3ea; background: #f2f5f8;
             text-align: center; font-size: 5.5pt; color: #5b6875; padding-top: 24px; }
    .status { font-size: 6.5pt; font-weight: bold; letter-spacing: .06em; text-transform: uppercase; }
</style>
</head>
<body>
<div class="card">
    <div class="head">
        <div class="mall">{{ $allocation->mall?->name }}</div>
        <div class="kind">Shop Owner Allocation Card</div>
    </div>

    <table>
        <tr>
            <td style="width:54px">
                <div class="photo">PHOTO</div>
            </td>
            <td style="padding-left:6px">
                <div class="k">Owner</div>
                <div class="v">{{ $allocation->owner?->full_name }}</div>
                <div class="k">Shop</div>
                <div class="v">{{ $allocation->shop?->shop_number }} &middot; {{ $allocation->shop?->block?->name ?? '—' }}</div>
                <div class="k">Allocation reference</div>
                <div class="v mono" style="font-size:7pt">{{ $allocation->reference_number }}</div>
                <div class="k">Card number / Issued</div>
                <div class="v mono" style="font-size:7pt">{{ $card->card_number }} &middot; {{ $card->issue_date->format('d M Y') }}</div>
                <div class="status" style="color:{{ $card->status === 'active' ? '#1b7f4b' : '#b3261e' }}">
                    {{ strtoupper($card->status) }}
                </div>
            </td>
            <td class="center" style="width:74px">
                {{-- FR-080 - QR verification. --}}
                <img src="{{ $qr }}" alt="Verify" style="width:70px;height:70px">
                <div style="font-size:5.5pt;color:#5b6875">Scan to verify</div>
            </td>
        </tr>
    </table>
</div>
</body></html>
