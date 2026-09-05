{{-- SRS 3.1 - navigation is filtered by the signed-in user's permissions (FR-011). --}}
<nav class="sidebar">
    <div class="group">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
    </div>

    @canany([\App\Support\Permissions::MALLS_VIEW, \App\Support\Permissions::SHOPS_VIEW])
        <div class="group">
            <div class="group-title">Mall</div>
            @can(\App\Support\Permissions::MALLS_VIEW)
                <a href="{{ route('admin.malls.index') }}" class="{{ request()->routeIs('admin.malls.*') ? 'active' : '' }}">Malls &amp; Structure</a>
            @endcan
            @can(\App\Support\Permissions::SHOPS_VIEW)
                <a href="{{ route('admin.shops.index') }}" class="{{ request()->routeIs('admin.shops.*') ? 'active' : '' }}">Shops</a>
            @endcan
        </div>
    @endcanany

    @canany([\App\Support\Permissions::OWNERS_VIEW, \App\Support\Permissions::ALLOCATIONS_VIEW, \App\Support\Permissions::TRANSFERS_VIEW])
        <div class="group">
            <div class="group-title">Allocation</div>
            @can(\App\Support\Permissions::OWNERS_VIEW)
                <a href="{{ route('admin.owners.index') }}" class="{{ request()->routeIs('admin.owners.*') ? 'active' : '' }}">Owners</a>
            @endcan
            @can(\App\Support\Permissions::ALLOCATIONS_VIEW)
                <a href="{{ route('admin.allocations.index') }}" class="{{ request()->routeIs('admin.allocations.*') ? 'active' : '' }}">Allocations</a>
            @endcan
            @can(\App\Support\Permissions::TRANSFERS_VIEW)
                <a href="{{ route('admin.transfers.index') }}" class="{{ request()->routeIs('admin.transfers.*') ? 'active' : '' }}">Ownership Transfers</a>
            @endcan
        </div>
    @endcanany

    @canany([\App\Support\Permissions::BILLING_VIEW, \App\Support\Permissions::PAYMENTS_VIEW])
        <div class="group">
            <div class="group-title">Finance</div>
            @can(\App\Support\Permissions::BILLING_VIEW)
                <a href="{{ route('admin.invoices.index') }}" class="{{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">Invoices</a>
            @endcan
            @can(\App\Support\Permissions::PAYMENTS_VIEW)
                <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">Payments &amp; Receipts</a>
            @endcan
        </div>
    @endcanany

    @canany([\App\Support\Permissions::NOTIFICATIONS_SEND, \App\Support\Permissions::TICKETS_VIEW])
        <div class="group">
            <div class="group-title">Communication</div>
            @can(\App\Support\Permissions::NOTIFICATIONS_SEND)
                <a href="{{ route('admin.notifications.index') }}" class="{{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">Notifications</a>
            @endcan
            @can(\App\Support\Permissions::TICKETS_VIEW)
                <a href="{{ route('admin.tickets.index') }}" class="{{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">Support Tickets</a>
            @endcan
        </div>
    @endcanany

    @canany([\App\Support\Permissions::REPORTS_VIEW, \App\Support\Permissions::AUDIT_VIEW])
        <div class="group">
            <div class="group-title">Oversight</div>
            @can(\App\Support\Permissions::REPORTS_VIEW)
                <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Reports</a>
            @endcan
            @can(\App\Support\Permissions::AUDIT_VIEW)
                <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">Audit Trail</a>
            @endcan
        </div>
    @endcanany

    @canany([\App\Support\Permissions::USERS_VIEW, \App\Support\Permissions::ROLES_MANAGE, \App\Support\Permissions::SETTINGS_MANAGE])
        <div class="group">
            <div class="group-title">Administration</div>
            @can(\App\Support\Permissions::USERS_VIEW)
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
            @endcan
            @can(\App\Support\Permissions::ROLES_MANAGE)
                <a href="{{ route('admin.roles.index') }}" class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">Roles &amp; Permissions</a>
            @endcan
            @can(\App\Support\Permissions::SETTINGS_MANAGE)
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">System Settings</a>
            @endcan
        </div>
    @endcanany

    <div class="group">
        <div class="group-title">Public</div>
        <a href="{{ route('verify.index') }}">Verification Portal</a>
    </div>
</nav>
