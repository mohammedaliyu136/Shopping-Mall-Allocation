@if (session('status'))
    <div class="alert ok">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert bad">
        <strong>{{ $errors->count() === 1 ? 'There is a problem:' : 'There are some problems:' }}</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
