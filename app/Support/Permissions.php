<?php

namespace App\Support;

use App\Models\Role;

/**
 * SRS FR-010 / FR-011 - the permission catalogue and the roles-to-permissions
 * matrix for the seven system users described in SRS section 5.
 *
 * Roles are stored in the database and remain editable at runtime; this class is
 * the shipped default matrix used to seed them.
 */
final class Permissions
{
    // User and access control
    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    // Mall and shops
    public const MALLS_VIEW = 'malls.view';

    public const MALLS_MANAGE = 'malls.manage';

    public const SHOPS_VIEW = 'shops.view';

    public const SHOPS_MANAGE = 'shops.manage';

    // Owners
    public const OWNERS_VIEW = 'owners.view';

    public const OWNERS_CREATE = 'owners.create';

    public const OWNERS_EDIT = 'owners.edit';

    // Allocations
    public const ALLOCATIONS_VIEW = 'allocations.view';

    public const ALLOCATIONS_CREATE = 'allocations.create';

    public const ALLOCATIONS_APPROVE = 'allocations.approve';

    public const ALLOCATIONS_CANCEL = 'allocations.cancel';

    public const DOCUMENTS_GENERATE = 'documents.generate';

    public const CARDS_REISSUE = 'cards.reissue';

    // Billing and payments
    public const BILLING_VIEW = 'billing.view';

    public const INVOICES_GENERATE = 'invoices.generate';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_RECORD = 'payments.record';

    // Transfers
    public const TRANSFERS_VIEW = 'transfers.view';

    public const TRANSFERS_CREATE = 'transfers.create';

    public const TRANSFERS_APPROVE = 'transfers.approve';

    // Communication and support
    public const NOTIFICATIONS_SEND = 'notifications.send';

    public const TICKETS_VIEW = 'tickets.view';

    public const TICKETS_MANAGE = 'tickets.manage';

    // Oversight
    public const REPORTS_VIEW = 'reports.view';

    public const AUDIT_VIEW = 'audit.view';

    /**
     * The full catalogue: permission slug => [display name, group].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function catalogue(): array
    {
        return [
            self::USERS_VIEW => ['View Users', 'Access Control'],
            self::USERS_MANAGE => ['Manage Users', 'Access Control'],
            self::ROLES_MANAGE => ['Manage Roles and Permissions', 'Access Control'],
            self::SETTINGS_MANAGE => ['Manage System Settings', 'Access Control'],

            self::MALLS_VIEW => ['View Malls', 'Mall'],
            self::MALLS_MANAGE => ['Manage Malls, Blocks and Floors', 'Mall'],
            self::SHOPS_VIEW => ['View Shops', 'Mall'],
            self::SHOPS_MANAGE => ['Manage Shops', 'Mall'],

            self::OWNERS_VIEW => ['View Owners', 'Owners'],
            self::OWNERS_CREATE => ['Create Owner', 'Owners'],
            self::OWNERS_EDIT => ['Edit Owner', 'Owners'],

            self::ALLOCATIONS_VIEW => ['View Allocations', 'Allocation'],
            self::ALLOCATIONS_CREATE => ['Allocate Shop', 'Allocation'],
            self::ALLOCATIONS_APPROVE => ['Approve Allocation', 'Allocation'],
            self::ALLOCATIONS_CANCEL => ['Cancel Allocation', 'Allocation'],
            self::DOCUMENTS_GENERATE => ['Generate Allocation Documents', 'Allocation'],
            self::CARDS_REISSUE => ['Reissue Allocation Card', 'Allocation'],

            self::BILLING_VIEW => ['View Billing', 'Finance'],
            self::INVOICES_GENERATE => ['Generate Invoice', 'Finance'],
            self::PAYMENTS_VIEW => ['View Payments', 'Finance'],
            self::PAYMENTS_RECORD => ['Record Payment', 'Finance'],

            self::TRANSFERS_VIEW => ['View Transfers', 'Transfer'],
            self::TRANSFERS_CREATE => ['Initiate Ownership Transfer', 'Transfer'],
            self::TRANSFERS_APPROVE => ['Approve Transfer', 'Transfer'],

            self::NOTIFICATIONS_SEND => ['Send Notifications', 'Communication'],
            self::TICKETS_VIEW => ['View Support Tickets', 'Support'],
            self::TICKETS_MANAGE => ['Manage Support Tickets', 'Support'],

            self::REPORTS_VIEW => ['View Reports', 'Oversight'],
            self::AUDIT_VIEW => ['View Audit Trail', 'Oversight'],
        ];
    }

    /** Every permission that only reads data - the auditor's read-only grant (SRS 5.6). */
    public static function readOnly(): array
    {
        return [
            self::USERS_VIEW, self::MALLS_VIEW, self::SHOPS_VIEW, self::OWNERS_VIEW,
            self::ALLOCATIONS_VIEW, self::BILLING_VIEW, self::PAYMENTS_VIEW,
            self::TRANSFERS_VIEW, self::TICKETS_VIEW, self::REPORTS_VIEW, self::AUDIT_VIEW,
        ];
    }

    /**
     * The default roles-to-permissions matrix (SRS section 5).
     *
     * @return array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    public static function matrix(): array
    {
        return [
            Role::SUPER_ADMIN => [
                'name' => 'Super Administrator',
                'description' => 'Complete access to the system, including configuration and access control.',
                // Granted implicitly by Role::hasPermission(), listed here so the matrix stays explicit.
                'permissions' => array_keys(self::catalogue()),
            ],

            Role::MALL_ADMIN => [
                'name' => 'Mall Administrator',
                'description' => 'Manages shops, owners, allocations, documents, notifications and general operations.',
                'permissions' => [
                    self::USERS_VIEW,
                    self::MALLS_VIEW, self::MALLS_MANAGE, self::SHOPS_VIEW, self::SHOPS_MANAGE,
                    self::OWNERS_VIEW, self::OWNERS_CREATE, self::OWNERS_EDIT,
                    self::ALLOCATIONS_VIEW, self::ALLOCATIONS_CREATE, self::ALLOCATIONS_APPROVE,
                    self::ALLOCATIONS_CANCEL, self::DOCUMENTS_GENERATE, self::CARDS_REISSUE,
                    self::BILLING_VIEW, self::PAYMENTS_VIEW,
                    self::TRANSFERS_VIEW, self::TRANSFERS_CREATE, self::TRANSFERS_APPROVE,
                    self::NOTIFICATIONS_SEND, self::TICKETS_VIEW, self::TICKETS_MANAGE,
                    self::REPORTS_VIEW, self::AUDIT_VIEW,
                ],
            ],

            Role::ALLOCATION_OFFICER => [
                'name' => 'Allocation Officer',
                'description' => 'Registers owners, manages allocations, generates documents and initiates transfers.',
                'permissions' => [
                    self::MALLS_VIEW, self::SHOPS_VIEW,
                    self::OWNERS_VIEW, self::OWNERS_CREATE, self::OWNERS_EDIT,
                    self::ALLOCATIONS_VIEW, self::ALLOCATIONS_CREATE, self::DOCUMENTS_GENERATE,
                    self::TRANSFERS_VIEW, self::TRANSFERS_CREATE,
                    self::REPORTS_VIEW,
                ],
            ],

            Role::FINANCE_OFFICER => [
                'name' => 'Finance Officer',
                'description' => 'Manages billing, invoices, payments, receipts and outstanding balances.',
                'permissions' => [
                    self::SHOPS_VIEW, self::OWNERS_VIEW, self::ALLOCATIONS_VIEW,
                    self::BILLING_VIEW, self::INVOICES_GENERATE,
                    self::PAYMENTS_VIEW, self::PAYMENTS_RECORD,
                    self::TRANSFERS_VIEW,
                    self::NOTIFICATIONS_SEND, self::REPORTS_VIEW,
                ],
            ],

            Role::SUPPORT_OFFICER => [
                'name' => 'Customer Support Officer',
                'description' => 'Views and responds to support tickets, assigns them and updates their status.',
                'permissions' => [
                    self::OWNERS_VIEW, self::SHOPS_VIEW, self::ALLOCATIONS_VIEW,
                    self::TICKETS_VIEW, self::TICKETS_MANAGE,
                    self::NOTIFICATIONS_SEND, self::REPORTS_VIEW,
                ],
            ],

            Role::AUDITOR => [
                'name' => 'Auditor',
                'description' => 'Read-only access to allocations, transfers, payments, billing and audit logs.',
                'permissions' => self::readOnly(),
            ],

            Role::SHOP_OWNER => [
                'name' => 'Shop Owner',
                'description' => 'Self-service portal access to their own allocation, billing, documents and tickets.',
                'permissions' => [],
            ],
        ];
    }
}
