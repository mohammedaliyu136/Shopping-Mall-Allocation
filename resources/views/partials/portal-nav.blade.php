{{-- SRS 3.2 - owner self-service navigation. --}}
@php
    $unread = \App\Models\NotificationRecipient::query()
        ->where('owner_id', auth()->user()->owner_id)
        ->whereNull('read_at')
        ->count();
@endphp
<nav class="sidebar">
    <div class="group">
        <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('portal.allocations.index') }}" class="{{ request()->routeIs('portal.allocations.*') ? 'active' : '' }}">My Shops</a>
    </div>
    <div class="group">
        <div class="group-title">Billing</div>
        <a href="{{ route('portal.invoices.index') }}" class="{{ request()->routeIs('portal.invoices.*') ? 'active' : '' }}">Invoices</a>
        <a href="{{ route('portal.payments.index') }}" class="{{ request()->routeIs('portal.payments.*') ? 'active' : '' }}">Payments &amp; Receipts</a>
    </div>
    <div class="group">
        <div class="group-title">Services</div>
        <a href="{{ route('portal.transfers.index') }}" class="{{ request()->routeIs('portal.transfers.*') ? 'active' : '' }}">Ownership Transfer</a>
        <a href="{{ route('portal.tickets.index') }}" class="{{ request()->routeIs('portal.tickets.*') ? 'active' : '' }}">Support Tickets</a>
        <a href="{{ route('portal.notifications.index') }}" class="{{ request()->routeIs('portal.notifications.*') ? 'active' : '' }}">
            Notifications @if ($unread)<span class="count">{{ $unread }}</span>@endif
        </a>
    </div>
    <div class="group">
        <div class="group-title">Account</div>
        <a href="{{ route('portal.profile.edit') }}" class="{{ request()->routeIs('portal.profile.*') ? 'active' : '' }}">My Details</a>
        <a href="{{ route('verify.index') }}">Verify a Document</a>
    </div>
</nav>
