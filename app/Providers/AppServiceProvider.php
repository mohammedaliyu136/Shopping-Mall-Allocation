<?php

namespace App\Providers;

use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPermissionGates();
        $this->registerOwnerPortalGates();
        $this->applySessionLifetime();
    }

    /**
     * FR-011 - every permission in the catalogue becomes a Gate ability, so
     * views and controllers can use @can / $this->authorize() uniformly.
     */
    private function registerPermissionGates(): void
    {
        foreach (array_keys(Permissions::catalogue()) as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
    }

    /**
     * SRS 3.2 - owner portal accounts may only reach their own records.
     */
    private function registerOwnerPortalGates(): void
    {
        Gate::define('view-allocation', fn ($user, Allocation $allocation) => $user->isShopOwner()
            ? $user->owner_id === $allocation->owner_id
            : $user->hasPermission(Permissions::ALLOCATIONS_VIEW));

        Gate::define('view-invoice', fn ($user, Invoice $invoice) => $user->isShopOwner()
            ? $user->owner_id === $invoice->owner_id
            : $user->hasPermission(Permissions::BILLING_VIEW));

        Gate::define('view-payment', fn ($user, Payment $payment) => $user->isShopOwner()
            ? $user->owner_id === $payment->owner_id
            : $user->hasPermission(Permissions::PAYMENTS_VIEW));
    }

    /**
     * FR-003 - the configured inactivity timeout also drives the cookie lifetime.
     * Guarded so that console commands can run before the database exists.
     */
    private function applySessionLifetime(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            try {
                if (Schema::hasTable('system_settings')) {
                    $minutes = (int) SystemSetting::get('session.timeout_minutes', 0);

                    if ($minutes > 0) {
                        config(['session.lifetime' => $minutes]);
                    }
                }
            } catch (\Throwable) {
                // The database is not reachable yet; keep the configured default.
            }
        }
    }
}
