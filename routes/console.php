<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| SRS FR-092 - annual billing runs automatically at the start of the
| configured billing cycle, and FR-094 - overdue invoices are identified and
| the owners reminded daily.
|
| Both commands are safe to re-run: billing skips allocations already billed
| for the period, and an owner is reminded about a given invoice only once.
*/

Schedule::command('mall:run-billing')->yearlyOn(1, 1, '01:00');
Schedule::command('mall:process-overdue')->dailyAt('06:00');
