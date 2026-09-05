@extends('layouts.public')
@section('title', 'Verify an allocation document')

@section('content')
    {{-- SRS 3.3 / FR-081 - public verification, no login required. --}}
    <div class="card">
        <div class="card-head"><h2>Verify an allocation document</h2></div>
        <div class="card-body">
            <p class="small muted">
                Scan the QR code on an allocation letter or allocation card, or type the reference
                printed on the document below.
            </p>
            <form method="POST" action="{{ route('verify.lookup') }}">
                @csrf
                <div class="field">
                    <label for="reference">Allocation reference, document number or card number</label>
                    <input id="reference" type="text" name="reference" value="{{ old('reference') }}"
                           placeholder="e.g. GMP/2026/BLK-A/000125" required autofocus>
                </div>
                <button type="submit" class="btn">Verify</button>
            </form>
        </div>
    </div>
@endsection
