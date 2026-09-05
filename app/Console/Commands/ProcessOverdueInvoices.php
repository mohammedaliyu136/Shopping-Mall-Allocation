<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

/** SRS FR-094 / FR-133 - identify overdue invoices and remind the owners. */
class ProcessOverdueInvoices extends Command
{
    protected $signature = 'mall:process-overdue';

    protected $description = 'Mark unsettled invoices past their due date as overdue and notify the owners';

    public function handle(BillingService $billing): int
    {
        $count = $billing->flagOverdueInvoices();

        $this->info("{$count} overdue reminder(s) sent.");

        return self::SUCCESS;
    }
}
