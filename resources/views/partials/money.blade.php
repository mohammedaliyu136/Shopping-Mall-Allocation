{{-- Formats a money value using the configured currency symbol (SRS 8). --}}
<span class="nowrap">{{ \App\Models\SystemSetting::get('billing.currency_symbol', '') }}{{ number_format((float) $amount, 2) }}</span>
