<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The reference data every installation needs, followed by a worked
     * demonstration data set.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SystemSettingSeeder::class,
            BillingCategorySeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
