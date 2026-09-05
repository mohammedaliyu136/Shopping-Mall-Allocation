@extends('layouts.app')
@section('title', 'New invoice')

@section('content')
    <div class="page-head"><h1>Generate invoice</h1></div>

    <div class="card" style="max-width:860px"><div class="card-body">
        <form method="POST" action="{{ route('admin.invoices.store') }}">
            @csrf
            <div class="field"><label for="allocation_id">Allocation</label>
                <select id="allocation_id" name="allocation_id" required>
                    <option value="">Select an allocation…</option>
                    @foreach ($allocations as $allocation)
                        <option value="{{ $allocation->id }}" @selected(old('allocation_id', $selectedAllocation) == $allocation->id)>
                            {{ $allocation->reference_number }} — {{ $allocation->owner?->full_name }} ({{ $allocation->shop?->label() }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid cols-3">
                <div class="field"><label for="billing_period_start">Period start</label>
                    <input id="billing_period_start" type="date" name="billing_period_start"
                           value="{{ old('billing_period_start', now()->startOfYear()->toDateString()) }}" required></div>
                <div class="field"><label for="billing_period_end">Period end</label>
                    <input id="billing_period_end" type="date" name="billing_period_end"
                           value="{{ old('billing_period_end', now()->endOfYear()->toDateString()) }}" required></div>
                <div class="field"><label for="due_date">Due date</label>
                    <input id="due_date" type="date" name="due_date"
                           value="{{ old('due_date', now()->addDays((int) \App\Models\SystemSetting::get('billing.due_days', 30))->toDateString()) }}" required></div>
            </div>

            <fieldset>
                <legend>Fees</legend>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Category</th><th>Description</th><th class="num">Quantity</th><th class="num">Unit amount</th></tr></thead>
                        <tbody>
                        @for ($i = 0; $i < 4; $i++)
                            <tr>
                                <td>
                                    <select name="items[{{ $i }}][billing_category_id]">
                                        <option value="">—</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected(old("items.$i.billing_category_id") == $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="items[{{ $i }}][description]" value="{{ old("items.$i.description") }}"
                                           placeholder="{{ $i === 0 ? 'Annual rent' : 'Leave blank to skip' }}"></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", 1) }}"></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_amount]" value="{{ old("items.$i.unit_amount") }}"></td>
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
                <p class="hint">Fill in as many rows as you need — blank rows are ignored. A row you use needs a description, a quantity and an amount.</p>
            </fieldset>

            <div class="actions">
                <button type="submit" class="btn">Generate invoice</button>
                <a href="{{ route('admin.invoices.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
