<?php

namespace Tests;

use App\Models\Allocation;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Mall;
use App\Models\Owner;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Services\AllocationService;
use App\Services\ReferenceGenerator;
use Database\Seeders\BillingCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    /** Seeds the reference data every installation needs: roles, permissions, settings, billing categories. */
    protected function seedReferenceData(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            SystemSettingSeeder::class,
            BillingCategorySeeder::class,
        ]);
    }

    /** A staff account holding the given role. */
    protected function staff(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::create(array_merge([
            'name' => ucwords(str_replace('-', ' ', $roleSlug)),
            'email' => $roleSlug.'-'.uniqid().'@mall.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    /** A portal account linked to an owner record. */
    protected function ownerUser(Owner $owner): User
    {
        return User::create([
            'name' => $owner->full_name,
            'email' => $owner->email ?? 'owner-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role_id' => Role::query()->where('slug', Role::SHOP_OWNER)->value('id'),
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    protected function makeMall(array $attributes = []): Mall
    {
        $mall = Mall::create(array_merge([
            'name' => 'Test Plaza',
            'code' => 'TP'.random_int(100, 999),
            'address' => '1 Test Road',
            'status' => 'active',
        ], $attributes));

        $block = Block::create(['mall_id' => $mall->id, 'name' => 'Block A', 'code' => 'BLK-A', 'status' => 'active']);
        Floor::create(['block_id' => $block->id, 'name' => 'Ground Floor', 'code' => 'GF', 'level' => 0]);

        return $mall->fresh('blocks.floors');
    }

    protected function makeShop(?Mall $mall = null, array $attributes = []): Shop
    {
        $mall ??= $this->makeMall();
        $block = $mall->blocks()->first();

        return Shop::create(array_merge([
            'mall_id' => $mall->id,
            'block_id' => $block?->id,
            'floor_id' => $block?->floors()->value('id'),
            'shop_code' => 'SHOP-'.uniqid(),
            'shop_number' => 'A-'.random_int(1000, 9999),
            'shop_type' => 'retail',
            'size_sqm' => 36,
            'status' => Shop::AVAILABLE,
            'annual_rent' => 1200000,
            'service_charge' => 240000,
            'other_fees' => 60000,
        ], $attributes));
    }

    protected function makeOwner(array $attributes = []): Owner
    {
        return Owner::create(array_merge([
            'owner_code' => app(ReferenceGenerator::class)->ownerCode(),
            'full_name' => 'Test Owner '.uniqid(),
            'phone' => '+234 800 '.random_int(1000000, 9999999),
            'email' => 'owner'.uniqid().'@example.test',
            'address' => '2 Test Street',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Drives an allocation all the way through the SRS workflow: draft →
     * submitted → every approval stage → active, with documents issued.
     */
    protected function activeAllocation(?Shop $shop = null, ?Owner $owner = null, ?User $approver = null): Allocation
    {
        $service = app(AllocationService::class);

        $shop ??= $this->makeShop();
        $owner ??= $this->makeOwner();
        $approver ??= $this->staff(Role::MALL_ADMIN);

        $allocation = $service->create($shop, $owner, ['allocation_date' => Carbon::today()]);
        $service->submitForApproval($allocation);

        foreach ($service->approvalStages() as $stage) {
            $allocation = $service->approve($allocation->fresh(), $approver, "Approved at {$stage}");
        }

        return $service->activate($allocation->fresh())->fresh(['shop', 'owner', 'cards', 'documents']);
    }
}
