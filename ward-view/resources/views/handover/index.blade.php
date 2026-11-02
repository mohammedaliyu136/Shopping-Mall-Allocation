@extends('layouts.app')
@section('title', 'Handover · '.$ward->name)
@section('content')
<div class="row" style="margin-bottom:12px">
    <div class="grow"><h1>Handover &amp; tasks</h1><div class="muted">{{ $ward->name }} — handed over for the whole patient list.</div></div>
    <a class="btn secondary" href="{{ route('wards.show', $ward) }}">Back to ward</a>
</div>
<div class="grid cols-2">
    <section class="panel">
        <h2>Tasks</h2>
        @foreach (['nurse' => 'Nursing tasks', 'doctor' => 'Doctor tasks'] as $r => $title)
            <h3 style="margin-top:10px">{{ $title }} @if ($r === $role)<span class="pill blue">yours</span>@endif</h3>
            @forelse ($tasks->get($r, collect()) as $task)
                <form method="POST" action="{{ route('tasks.toggle', [$ward, $task]) }}" class="row" style="gap:8px;padding:6px 0;border-bottom:1px solid var(--line)">
                    @csrf
                    <input type="checkbox" @checked($task->done_at) onchange="this.form.submit()" @disabled(! auth()->user()->isClinical()) aria-label="Done">
                    <div class="grow">
                        <span style="{{ $task->done_at ? 'text-decoration:line-through;color:var(--muted)' : '' }}">{{ $task->title }}</span><br>
                        <small class="muted">
                            @if ($task->encounter){{ $task->encounter->patient->name }}{{ $task->encounter->bed ? ' · '.$task->encounter->bed->label : '' }} · @endif
                            {{ $task->creator->name }}
                            @if ($task->due_at) · due <span class="{{ ! $task->done_at && $task->due_at->isPast() ? 'pill red' : '' }}">{{ $task->due_at->format('H:i j M') }}</span>@endif
                            @if ($task->done_at) · done by {{ $task->completer?->name }} {{ $task->done_at->format('H:i') }}@endif
                        </small>
                    </div>
                </form>
            @empty
                <p class="muted">No tasks.</p>
            @endforelse
        @endforeach
        @if (auth()->user()->isClinical())
            <form method="POST" action="{{ route('tasks.store', $ward) }}" style="margin-top:12px">
                @csrf
                <label>New task</label><input type="text" name="title" required>
                <div class="three">
                    <div><label>For</label><select name="for_role"><option value="nurse">Nurses</option><option value="doctor">Doctors</option></select></div>
                    <div><label>Patient</label><select name="encounter_id"><option value="">Whole ward</option>@foreach ($encounters as $e)<option value="{{ $e->id }}">{{ $e->bed?->label }} {{ $e->patient->name }}</option>@endforeach</select></div>
                    <div><label>Due</label><input type="datetime-local" name="due_at"></div>
                </div>
                <p><button class="btn sm" type="submit">Add task</button></p>
            </form>
        @endif
    </section>
    <section class="panel">
        <h2>Handover notes</h2>
        @if (auth()->user()->isClinical())
            <form method="POST" action="{{ route('handover.store', $ward) }}">
                @csrf
                <div class="two">
                    <div><label>Shift</label><select name="shift"><option value="day">Day</option><option value="evening">Evening</option><option value="night">Night</option></select></div>
                </div>
                <label>Handover</label><textarea name="body" style="min-height:140px" required placeholder="Bed by bed: concerns, outstanding jobs, escalation plans"></textarea>
                <p><button class="btn sm" type="submit">Save handover</button></p>
            </form>
        @endif
        @forelse ($notes as $n)
            <article style="border-top:1px solid var(--line);padding:10px 0">
                <small class="muted">{{ ucfirst($n->shift) }} · {{ ucfirst($n->role) }} · {{ $n->author->name }} · {{ $n->created_at->format('H:i j M') }}</small>
                <div style="white-space:pre-line">{{ $n->body }}</div>
            </article>
        @empty
            <p class="muted">No handover in the last 48 hours.</p>
        @endforelse
    </section>
</div>
@endsection
