<?php

use App\Http\Controllers\Admin\AllocationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MallController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShopController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Portal;
use App\Http\Controllers\VerificationController;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public verification portal (SRS 3.3, FR-080 to FR-083)
|--------------------------------------------------------------------------
| No login is required for basic document verification.
*/

Route::get('/', fn () => redirect()->route('verify.index'))->name('home');

Route::get('/verify', [VerificationController::class, 'index'])->name('verify.index');
Route::post('/verify', [VerificationController::class, 'lookup'])->name('verify.lookup');
Route::get('/verify/{token}', [VerificationController::class, 'show'])->name('verify.show');

/*
|--------------------------------------------------------------------------
| Authentication (SRS 6.1)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');

    // FR-002 password reset
    Route::get('/forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendResetLink'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // FR-002 password change
    Route::get('/password/change', [PasswordController::class, 'changeForm'])->name('password.change');
    Route::put('/password/change', [PasswordController::class, 'change'])->name('password.change.update');

    /*
    |----------------------------------------------------------------------
    | Document downloads (FR-061, FR-070, FR-091, FR-110)
    |----------------------------------------------------------------------
    | Shared by the administration and owner portals; each action authorises
    | the caller against the record it is asked for.
    */
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('allocations/{allocation}/letter', [DocumentController::class, 'allocationLetter'])->name('letter');
        Route::get('allocations/{allocation}/card', [DocumentController::class, 'allocationCard'])->name('card');
        Route::get('invoices/{invoice}', [DocumentController::class, 'invoice'])->name('invoice');
        Route::get('receipts/{receipt}', [DocumentController::class, 'receipt'])->name('receipt');
    });
});

/*
|--------------------------------------------------------------------------
| Administration portal (SRS 3.1)
|--------------------------------------------------------------------------
| Every route is gated on the permission its feature requires (FR-011).
*/

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Mall structure - FR-020, FR-021
    Route::middleware('permission:'.P::MALLS_VIEW)->group(function () {
        Route::get('malls', [MallController::class, 'index'])->name('malls.index');
        Route::get('malls/{mall}', [MallController::class, 'show'])->name('malls.show');
    });
    Route::middleware('permission:'.P::MALLS_MANAGE)->group(function () {
        Route::get('malls-create', [MallController::class, 'create'])->name('malls.create');
        Route::post('malls', [MallController::class, 'store'])->name('malls.store');
        Route::get('malls/{mall}/edit', [MallController::class, 'edit'])->name('malls.edit');
        Route::put('malls/{mall}', [MallController::class, 'update'])->name('malls.update');
        Route::post('malls/{mall}/blocks', [MallController::class, 'storeBlock'])->name('malls.blocks.store');
        Route::post('blocks/{block}/floors', [MallController::class, 'storeFloor'])->name('blocks.floors.store');
    });

    // Shops - FR-030 to FR-032
    Route::middleware('permission:'.P::SHOPS_VIEW)->group(function () {
        Route::get('shops', [ShopController::class, 'index'])->name('shops.index');
        Route::get('shops/{shop}', [ShopController::class, 'show'])->name('shops.show');
    });
    Route::middleware('permission:'.P::SHOPS_MANAGE)->group(function () {
        Route::get('shops-create', [ShopController::class, 'create'])->name('shops.create');
        Route::post('shops', [ShopController::class, 'store'])->name('shops.store');
        Route::get('shops/{shop}/edit', [ShopController::class, 'edit'])->name('shops.edit');
        Route::put('shops/{shop}', [ShopController::class, 'update'])->name('shops.update');
    });

    // Owners - FR-040 to FR-043
    Route::middleware('permission:'.P::OWNERS_VIEW)->group(function () {
        Route::get('owners', [OwnerController::class, 'index'])->name('owners.index');
        Route::get('owners/{owner}', [OwnerController::class, 'show'])->name('owners.show');
        Route::get('owners/{owner}/documents/{document}', [OwnerController::class, 'downloadDocument'])
            ->name('owners.documents.download');
    });
    Route::middleware('permission:'.P::OWNERS_CREATE)->group(function () {
        Route::get('owners-create', [OwnerController::class, 'create'])->name('owners.create');
        Route::post('owners', [OwnerController::class, 'store'])->name('owners.store');
    });
    Route::middleware('permission:'.P::OWNERS_EDIT)->group(function () {
        Route::get('owners/{owner}/edit', [OwnerController::class, 'edit'])->name('owners.edit');
        Route::put('owners/{owner}', [OwnerController::class, 'update'])->name('owners.update');
        Route::post('owners/{owner}/documents', [OwnerController::class, 'storeDocument'])->name('owners.documents.store');
    });

    // Allocations - FR-050 to FR-072
    Route::middleware('permission:'.P::ALLOCATIONS_VIEW)->group(function () {
        Route::get('allocations', [AllocationController::class, 'index'])->name('allocations.index');
        Route::get('allocations/{allocation}', [AllocationController::class, 'show'])->name('allocations.show');
    });
    Route::middleware('permission:'.P::ALLOCATIONS_CREATE)->group(function () {
        Route::get('allocations-create', [AllocationController::class, 'create'])->name('allocations.create');
        Route::post('allocations', [AllocationController::class, 'store'])->name('allocations.store');
        Route::post('allocations/{allocation}/submit', [AllocationController::class, 'submit'])->name('allocations.submit');
    });
    Route::middleware('permission:'.P::ALLOCATIONS_APPROVE)->group(function () {
        Route::post('allocations/{allocation}/approve', [AllocationController::class, 'approve'])->name('allocations.approve');
        Route::post('allocations/{allocation}/reject', [AllocationController::class, 'reject'])->name('allocations.reject');
        Route::post('allocations/{allocation}/activate', [AllocationController::class, 'activate'])->name('allocations.activate');
    });
    Route::middleware('permission:'.P::ALLOCATIONS_CANCEL)->group(function () {
        Route::post('allocations/{allocation}/cancel', [AllocationController::class, 'cancel'])->name('allocations.cancel');
        Route::post('allocations/{allocation}/suspend', [AllocationController::class, 'suspend'])->name('allocations.suspend');
        Route::post('allocations/{allocation}/reinstate', [AllocationController::class, 'reinstate'])->name('allocations.reinstate');
    });
    Route::post('allocations/{allocation}/reissue-card', [AllocationController::class, 'reissueCard'])
        ->middleware('permission:'.P::CARDS_REISSUE)->name('allocations.cards.reissue');

    // Billing - FR-090 to FR-094
    Route::middleware('permission:'.P::BILLING_VIEW)->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });
    Route::middleware('permission:'.P::INVOICES_GENERATE)->group(function () {
        Route::get('invoices-create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('invoices/run-billing', [InvoiceController::class, 'runRecurring'])->name('invoices.run-billing');
        Route::post('invoices/flag-overdue', [InvoiceController::class, 'flagOverdue'])->name('invoices.flag-overdue');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });

    // Payments - FR-100 to FR-110
    Route::middleware('permission:'.P::PAYMENTS_VIEW)->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });
    Route::middleware('permission:'.P::PAYMENTS_RECORD)->group(function () {
        Route::get('invoices/{invoice}/pay', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('invoices/{invoice}/pay', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    });

    // Ownership transfers - FR-120 to FR-124
    Route::middleware('permission:'.P::TRANSFERS_VIEW)->group(function () {
        Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    });
    Route::middleware('permission:'.P::TRANSFERS_CREATE)->group(function () {
        Route::get('transfers-create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('transfers/{transfer}/documents', [TransferController::class, 'storeDocument'])->name('transfers.documents.store');
    });
    Route::middleware('permission:'.P::TRANSFERS_APPROVE)->group(function () {
        Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve'])->name('transfers.approve');
        Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
    });

    // Notifications - FR-130 to FR-133
    Route::middleware('permission:'.P::NOTIFICATIONS_SEND)->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications-create', [NotificationController::class, 'create'])->name('notifications.create');
        Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
        Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
        Route::post('notifications/{notification}/send', [NotificationController::class, 'send'])->name('notifications.send');
    });

    // Support tickets - FR-140 to FR-143
    Route::middleware('permission:'.P::TICKETS_VIEW)->group(function () {
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    });
    Route::middleware('permission:'.P::TICKETS_MANAGE)->group(function () {
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
    });

    // Reports - FR-160 to FR-163
    Route::middleware('permission:'.P::REPORTS_VIEW)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('allocations', [ReportController::class, 'allocations'])->name('allocations');
        Route::get('financial', [ReportController::class, 'financial'])->name('financial');
        Route::get('owners', [ReportController::class, 'owners'])->name('owners');
        Route::get('support', [ReportController::class, 'support'])->name('support');
        Route::get('{report}/export', [ReportController::class, 'export'])->name('export');
    });

    // Audit trail - FR-170, FR-171
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:'.P::AUDIT_VIEW)->name('audit.index');

    // Users, roles and settings - FR-010, FR-011
    Route::middleware('permission:'.P::USERS_VIEW)->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('permission:'.P::USERS_MANAGE)->group(function () {
        Route::get('users-create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::middleware('permission:'.P::ROLES_MANAGE)->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles-create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
    Route::middleware('permission:'.P::SETTINGS_MANAGE)->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});

/*
|--------------------------------------------------------------------------
| Owner self-service portal (SRS 3.2, 6.16)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'shop-owner'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [Portal\DashboardController::class, 'index'])->name('dashboard');

    Route::get('allocations', [Portal\AllocationController::class, 'index'])->name('allocations.index');
    Route::get('allocations/{allocation}', [Portal\AllocationController::class, 'show'])->name('allocations.show');

    Route::get('invoices', [Portal\BillingController::class, 'invoices'])->name('invoices.index');
    Route::get('invoices/{invoice}', [Portal\BillingController::class, 'showInvoice'])->name('invoices.show');
    Route::get('payments', [Portal\BillingController::class, 'payments'])->name('payments.index');

    // FR-100 / FR-103 - the owner initiates an online payment for an invoice.
    Route::post('invoices/{invoice}/pay', [Portal\BillingController::class, 'pay'])->name('invoices.pay');

    // FR-120 - owners may request an ownership transfer.
    Route::get('transfers', [Portal\TransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers-create', [Portal\TransferController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [Portal\TransferController::class, 'store'])->name('transfers.store');

    // FR-140 - owners raise and follow up support tickets.
    Route::get('tickets', [Portal\TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets-create', [Portal\TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [Portal\TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [Portal\TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/reply', [Portal\TicketController::class, 'reply'])->name('tickets.reply');

    Route::get('notifications', [Portal\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{recipient}/read', [Portal\NotificationController::class, 'markRead'])->name('notifications.read');

    // FR-152 - profile management.
    Route::get('profile', [Portal\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [Portal\ProfileController::class, 'update'])->name('profile.update');
});
