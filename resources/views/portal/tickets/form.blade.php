@extends('layouts.app')
@section('title', 'Raise a ticket')

@section('content')
    <div class="page-head"><h1>Raise a support ticket</h1></div>

    <div class="card" style="max-width:620px"><div class="card-body">
        <form method="POST" action="{{ route('portal.tickets.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid cols-2">
                <div class="field"><label for="category">Category</label>
                    <select id="category" name="category" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ str($category)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select></div>
                <div class="field"><label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select></div>
            </div>

            <div class="field"><label for="shop_id">Shop concerned</label>
                <select id="shop_id" name="shop_id">
                    <option value="">—</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->label() }}</option>
                    @endforeach
                </select></div>

            <div class="field"><label for="subject">Subject</label>
                <input id="subject" type="text" name="subject" value="{{ old('subject') }}" required></div>

            <div class="field"><label for="description">Description</label>
                <textarea id="description" name="description" rows="6" required>{{ old('description') }}</textarea></div>

            <div class="field"><label for="attachment">Attachment</label>
                <input id="attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                <div class="hint">Optional. PDF or image, up to 10 MB.</div></div>

            <div class="actions">
                <button type="submit" class="btn">Submit ticket</button>
                <a href="{{ route('portal.tickets.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
