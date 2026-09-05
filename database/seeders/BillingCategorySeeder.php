<?php

namespace Database\Seeders;

use App\Models\BillingCategory;
use Illuminate\Database\Seeder;

/** SRS FR-090 - the billing categories the system supports out of the box. */
class BillingCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Annual Rent', 'annual-rent', true, 'Yearly rent payable on an allocated shop'],
            ['Service Charge', 'service-charge', true, 'Shared services and facility upkeep'],
            ['Maintenance Fee', 'maintenance-fee', true, 'Scheduled maintenance of common areas'],
            ['Electricity', 'electricity', false, 'Metered or apportioned electricity'],
            ['Water', 'water', false, 'Water supply charges'],
            ['Security', 'security', true, 'Mall security services'],
            ['Parking', 'parking', false, 'Allocated parking space'],
            ['Penalty', 'penalty', false, 'Late payment or breach penalty'],
            ['Other Fees', 'other-fees', false, 'Any other applicable charge'],
        ];

        foreach ($categories as [$name, $code, $recurring, $description]) {
            BillingCategory::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_recurring' => $recurring,
                    'is_active' => true,
                ],
            );
        }
    }
}
