<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** SRS FR-092 - automatic recurring billing. */
class RunAnnualBilling extends Command
{
    protected $signature = 'mall:run-billing {--period-start= : First day of the billing period (defaults to the configured cycle start)}';

    protected $description = 'Generate annual invoices for every active allocation';

    public function handle(BillingService $billing): int
    {
        $periodStart = $this->option('period-start')
            ? Carbon::parse($this->option('period-start'))
            : Carbon::today()->startOfYear()->setMonth((int) SystemSetting::get('billing.cycle_start_month', 1))->startOfMonth();

        $this->info(sprintf('Running annual billing for the period beginning %s…', $periodStart->toFormattedDateString()));

        $invoices = $billing->runRecurringBilling($periodStart);

        $this->info(sprintf('%d active allocation(s) processed.', $invoices->count()));
        $this->line('Allocations already billed for this period were skipped.');

        return self::SUCCESS;
    }
}
