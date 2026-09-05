<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\AllocationApproval;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Services\AllocationService;
use App\Services\BillingService;
use App\Services\NotificationService;
use App\Services\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 6.1 (authentication), 6.2 (access control), 6.6 (approval workflow) and
 * 7.2 (security) - the requirements that sit behind the acceptance criteria.
 */
class SecurityAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    /** FR-001 - registered users sign in; bad credentials are refused and logged. */
    #[Test]
    public function users_sign_in_and_failed_attempts_are_logged(): void
    {
        $user = $this->staff(Role::FINANCE_OFFICER, ['email' => 'finance@mall.test']);

        $this->post(route('login'), ['email' => 'finance@mall.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(AuditLog::query()->where('action', 'auth.login_failed')->exists());

        $this->post(route('login'), ['email' => 'finance@mall.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(AuditLog::query()->where('action', 'auth.login')->exists());
    }

    /** A deactivated account cannot sign in, and loses access immediately. */
    #[Test]
    public function deactivated_accounts_are_locked_out(): void
    {
        $user = $this->staff(Role::MALL_ADMIN, ['email' => 'admin@mall.test']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => 'admin@mall.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    /** FR-002 - password change, with the current password required. */
    #[Test]
    public function users_change_their_own_password(): void
    {
        $user = $this->staff(Role::MALL_ADMIN);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'not-the-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('admin.dashboard'));

        $user->refresh();

        // 7.2 - passwords are stored hashed, never in plain text.
        $this->assertTrue(Hash::check('a-brand-new-password', $user->password));
        $this->assertNotSame('a-brand-new-password', $user->password);
        $this->assertTrue(AuditLog::query()->where('action', 'password.changed')->exists());
    }

    /** FR-003 - a session is terminated after the configured period of inactivity. */
    #[Test]
    public function inactive_sessions_are_terminated(): void
    {
        SystemSetting::set('session.timeout_minutes', 30, 'integer', 'security');

        $user = $this->staff(Role::MALL_ADMIN);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        // Wind the last activity back beyond the configured timeout.
        $user->forceFill(['last_activity_at' => Carbon::now()->subMinutes(31)])->saveQuietly();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** FR-003 - the timeout is configurable, and activity keeps a session alive. */
    #[Test]
    public function activity_keeps_a_session_alive(): void
    {
        SystemSetting::set('session.timeout_minutes', 120, 'integer', 'security');

        $user = $this->staff(Role::MALL_ADMIN);
        $user->forceFill(['last_activity_at' => Carbon::now()->subMinutes(31)])->saveQuietly();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        $this->assertTrue(
            $user->fresh()->last_activity_at->greaterThan(Carbon::now()->subMinute()),
            'Each request refreshes the activity timestamp.',
        );
    }

    /** FR-053 - the allocation approval chain runs level by level. */
    #[Test]
    public function allocations_pass_through_every_approval_level(): void
    {
        $service = app(AllocationService::class);
        $approver = $this->staff(Role::MALL_ADMIN);

        $allocation = $service->create($this->makeShop(), $this->makeOwner());
        $service->submitForApproval($allocation);
        $allocation->refresh();

        $stages = $service->approvalStages();
        $this->assertCount(count($stages), $allocation->approvals);
        $this->assertSame(Allocation::PENDING_APPROVAL, $allocation->status);

        // Each approval clears one level only; the allocation stays pending until the last.
        for ($level = 1; $level < count($stages); $level++) {
            $allocation = $service->approve($allocation->fresh(), $approver);
            $this->assertSame(Allocation::PENDING_APPROVAL, $allocation->fresh()->status);
            $this->assertSame(
                $level,
                $allocation->approvals()->where('status', AllocationApproval::APPROVED)->count(),
            );
        }

        $allocation = $service->approve($allocation->fresh(), $approver);

        $this->assertSame(Allocation::APPROVED, $allocation->status);
        $this->assertNotNull($allocation->approved_at);
        // Documents are issued only once the chain completes.
        $this->assertTrue($allocation->documents()->exists());
        $this->assertTrue($allocation->cards()->exists());
    }

    /** FR-053 - the chain is configurable; a single stage means single-step approval. */
    #[Test]
    public function the_approval_chain_is_configurable(): void
    {
        SystemSetting::set('allocation.approval_stages', ['Mall Manager'], 'json', 'allocation');

        $service = app(AllocationService::class);
        $allocation = $service->create($this->makeShop(), $this->makeOwner());
        $service->submitForApproval($allocation);

        $allocation = $service->approve($allocation->fresh(), $this->staff(Role::MALL_ADMIN));

        $this->assertSame(Allocation::APPROVED, $allocation->status);
        $this->assertCount(1, $allocation->approvals);
    }

    /** FR-053 - a rejection cancels the allocation and releases the shop. */
    #[Test]
    public function a_rejected_allocation_is_cancelled_and_the_shop_released(): void
    {
        $service = app(AllocationService::class);
        $shop = $this->makeShop();

        $allocation = $service->create($shop, $this->makeOwner());
        $service->submitForApproval($allocation);
        $service->reject($allocation->fresh(), $this->staff(Role::MALL_ADMIN), 'Incomplete documentation');

        $this->assertSame(Allocation::CANCELLED, $allocation->fresh()->status);
        $this->assertSame(Shop::AVAILABLE, $shop->fresh()->status);
        $this->assertTrue($shop->fresh()->isAllocatable(), 'The shop can be allocated to someone else.');
    }

    /** FR-051 - allocation references are unique and follow the configured format. */
    #[Test]
    public function allocation_references_are_unique_and_configurable(): void
    {
        SystemSetting::set('allocation.reference_format', '{mall}-{year}-{block}-{sequence}', 'string', 'allocation');

        $mall = $this->makeMall(['code' => 'XYZ']);
        $service = app(AllocationService::class);
        $references = [];

        for ($i = 0; $i < 5; $i++) {
            $allocation = $service->create($this->makeShop($mall), $this->makeOwner());
            $references[] = $allocation->reference_number;
        }

        $this->assertCount(5, array_unique($references));
        $this->assertStringStartsWith('XYZ-'.now()->format('Y').'-BLK-A-', $references[0]);

        // 7.2 - verification tokens are unique and unguessable.
        $tokens = Allocation::query()->pluck('verification_token');
        $this->assertCount(5, $tokens->unique());
    }

    /** Reference generators never collide, even with values already in the table. */
    #[Test]
    public function sequential_references_skip_values_already_taken(): void
    {
        $generator = app(ReferenceGenerator::class);

        $year = now()->format('Y');

        $this->assertSame("INV-{$year}-000001", $generator->invoiceNumber());

        // Once that number is actually taken, the generator moves past it.
        $invoice = app(BillingService::class)->generateInvoice($this->activeAllocation());

        $this->assertSame("INV-{$year}-000001", $invoice->invoice_number);
        $this->assertSame("INV-{$year}-000002", $generator->invoiceNumber());
    }

    /** FR-131 - notifications can be aimed at the owners in one block. */
    #[Test]
    public function notifications_can_target_a_single_block(): void
    {
        $mall = $this->makeMall();
        $blockA = $mall->blocks()->first();
        $blockB = Block::create(['mall_id' => $mall->id, 'name' => 'Block B', 'code' => 'BLK-B', 'status' => 'active']);

        $inBlockA = $this->activeAllocation($this->makeShop($mall, ['block_id' => $blockA->id]))->owner;
        $this->activeAllocation($this->makeShop($mall, ['block_id' => $blockB->id]));

        $notification = app(NotificationService::class)->createAndSend(
            'Block A maintenance',
            'Water will be shut off in Block A on Saturday.',
            ['target_type' => Notification::TARGET_BLOCK, 'target_ref' => (string) $blockA->id],
        );

        $this->assertSame(1, $notification->recipients()->count());
        $this->assertSame($inBlockA->id, $notification->recipients()->first()->owner_id);
    }

    /** FR-132 - an email notification with no address on file is recorded as failed. */
    #[Test]
    public function undeliverable_notifications_are_recorded_as_failed(): void
    {
        $owner = $this->makeOwner(['email' => null]);

        $notification = app(NotificationService::class)->createAndSend(
            'Annual notice',
            'Please review your allocation terms.',
            ['channel' => Notification::EMAIL, 'target_type' => Notification::TARGET_ALL],
        );

        $recipient = $notification->recipients()->where('owner_id', $owner->id)->firstOrFail();

        $this->assertSame('failed', $recipient->status);
        $this->assertNotEmpty($recipient->error);
    }

    /** The public verification portal needs no login and exposes no private data. */
    #[Test]
    public function the_verification_portal_is_public_but_limited(): void
    {
        $allocation = $this->activeAllocation();

        $this->assertGuest();

        $this->get(route('verify.index'))->assertOk();

        $response = $this->get(route('verify.show', $allocation->verification_token));

        $response->assertOk()
            ->assertSee($allocation->owner->full_name)
            ->assertSee($allocation->reference_number);

        // Contact details, identification and financial position stay private.
        $response->assertDontSee($allocation->owner->phone)
            ->assertDontSee((string) $allocation->owner->address)
            ->assertDontSee((string) $allocation->annual_rent);
    }

    /** A document reference printed on a letter also resolves through the portal. */
    #[Test]
    public function documents_can_be_verified_by_their_printed_reference(): void
    {
        $allocation = $this->activeAllocation();
        $card = $allocation->cards()->firstOrFail();

        $this->post(route('verify.lookup'), ['reference' => $allocation->reference_number])
            ->assertRedirect(route('verify.show', $allocation->verification_token));

        $this->post(route('verify.lookup'), ['reference' => $card->card_number])
            ->assertRedirect(route('verify.show', $allocation->verification_token));

        $this->post(route('verify.lookup'), ['reference' => 'MALL/2026/NOPE/999999'])
            ->assertOk()
            ->assertSee('INVALID OR UNRECOGNISED ALLOCATION');
    }
}
