@extends('layouts.app')
@section('title', 'Change log · '.$ward->name)
@section('content')
<div class="row" style="margin-bottom:12px">
    <div class="grow"><h1>Change log: {{ $ward->name }}</h1></div>
    <a class="btn secondary" href="{{ route('admin.wards.edit', $ward) }}">Back to setup</a>
</div>
<section class="panel">
    @include('admin.wards._changes', ['changes' => $changes])
    {{ $changes->links() }}
</section>
@endsection
