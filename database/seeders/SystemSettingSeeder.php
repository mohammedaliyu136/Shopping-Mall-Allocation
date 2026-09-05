<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/** SRS 8 - the configurable settings the system ships with. */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // FR-051 - the allocation reference format is configurable.
            ['allocation.reference_format', '{mall}/{year}/{block}/{sequence}', 'string', 'allocation',
                'Placeholders: {mall}, {year}, {block}, {sequence}'],

            // FR-053 - the approval chain; a single stage disables multi-level approval.
            ['allocation.approval_stages', json_encode([
                'Allocation Officer', 'Mall Manager', 'Finance Confirmation', 'Final Approval',
            ]), 'json', 'allocation', 'Ordered list of approval stages'],

            ['allocation.default_terms',
                "1. The allocation is granted subject to payment of all applicable rent, service charges and fees.\n".
                "2. The shop shall be used only for the approved nature of business.\n".
                "3. The allocation may not be sublet or transferred without written approval of mall management.\n".
                "4. Annual rent and service charges are payable in advance on or before the due date.\n".
                '5. Mall management reserves the right to suspend or cancel this allocation for breach of these terms.',
                'string', 'allocation', 'Default terms printed on the allocation letter'],

            // FR-092 - billing period and due date are configurable.
            ['billing.due_days', '30', 'integer', 'billing', 'Days after issue that an invoice falls due'],
            ['billing.cycle_start_month', '1', 'integer', 'billing', 'Month the annual billing cycle starts'],
            ['billing.currency', 'NGN', 'string', 'billing', 'Currency code used on documents'],
            ['billing.currency_symbol', '₦', 'string', 'billing', 'Symbol shown on documents'],

            // FR-003 - configurable inactivity timeout.
            ['session.timeout_minutes', '30', 'integer', 'security', 'Inactive sessions are terminated after this many minutes'],

            ['mall.default_code', 'MALL', 'string', 'general', 'Fallback mall code used in references'],
        ];

        foreach ($settings as [$key, $value, $type, $group, $description]) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group, 'description' => $description],
            );
        }
    }
}
