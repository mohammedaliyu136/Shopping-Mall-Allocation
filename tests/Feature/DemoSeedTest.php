<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The demonstration data set is what anyone evaluating the system signs in to,
 * so the seeded accounts must actually work.
 */
class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    #[Test]
    public function seeded_accounts_can_sign_in_with_the_default_password(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->post(route('login'), ['email' => 'admin@mall.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    /**
     * A DEMO_PASSWORD key that is present but blank must not seed accounts with
     * an empty password - .env.example ships exactly that key, unset.
     */
    #[Test]
    public function a_blank_demo_password_falls_back_to_the_default(): void
    {
        config(['mall.demo_password' => env('DEMO_PASSWORD') ?: 'password']);

        $this->assertSame('password', config('mall.demo_password'));

        $this->seed(DemoDataSeeder::class);

        $admin = User::query()->where('email', 'admin@mall.test')->firstOrFail();

        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertFalse(Hash::check('', $admin->password), 'An empty password must never be accepted.');
    }

    #[Test]
    public function the_demo_password_can_be_overridden(): void
    {
        config(['mall.demo_password' => 'A-Different-Secret']);

        $this->seed(DemoDataSeeder::class);

        $this->post(route('login'), ['email' => 'admin@mall.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post(route('login'), ['email' => 'admin@mall.test', 'password' => 'A-Different-Secret'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    #[Test]
    public function every_documented_demo_account_exists_and_holds_the_right_role(): void
    {
        $this->seed(DemoDataSeeder::class);

        $expected = [
            'admin@mall.test' => Role::SUPER_ADMIN,
            'mall.admin@mall.test' => Role::MALL_ADMIN,
            'allocation@mall.test' => Role::ALLOCATION_OFFICER,
            'finance@mall.test' => Role::FINANCE_OFFICER,
            'support@mall.test' => Role::SUPPORT_OFFICER,
            'auditor@mall.test' => Role::AUDITOR,
            'aisha.bello@example.test' => Role::SHOP_OWNER,
        ];

        foreach ($expected as $email => $role) {
            $user = User::query()->with('role')->where('email', $email)->first();

            $this->assertNotNull($user, "The README documents {$email} as a demonstration account.");
            $this->assertSame($role, $user->role?->slug, "{$email} should hold the {$role} role.");
            $this->assertTrue($user->is_active);
        }

        // The shop-owner account must be linked to its owner record, or the
        // portal has nothing to show.
        $owner = User::query()->where('email', 'aisha.bello@example.test')->firstOrFail();
        $this->assertNotNull($owner->owner_id);
    }
}
