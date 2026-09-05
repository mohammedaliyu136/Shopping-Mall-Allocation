<?php

namespace Tests\Feature;

use App\Exceptions\AllocationException;
use App\Exceptions\BillingException;
use App\Exceptions\TransferException;
use App\Models\Allocation;
use App\Models\AllocationCard;
use App\Models\AllocationDocument;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Owner;
use App\Models\OwnershipTransfer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AllocationService;
use App\Services\BillingService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\SupportTicketService;
use App\Services\TransferService;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS section 14 - Acceptance Criteria.
 *
 * One test per numbered criterion. The system is deployable when these pass.
 */
class AcceptanceCriteriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    /** 1. Users can register and manage owners. */
    #[Test]
    public function users_can_register_and_manage_owners(): void
    {
        $officer = $this->staff(Role::ALLOCATION_OFFICER);

        $this->actingAs($officer)
            ->post(route('admin.owners.store'), [
                'full_name' => 'Aisha Bello',
                'gender' => 'female',
                'phone' => '+234 803 111 1111',
                'email' => 'aisha@example.test',
                'address' => '10 Market Road',
                'id_type' => 'national_id',
                'id_number' => 'NIN000111222',
                'business_name' => 'Bello Fabrics',
                'status' => 'active',
            ])
            ->assertRedirect();

        $owner = Owner::query()->where('email', 'aisha@example.test')->first();

        $this->assertNotNull($owner);
        $this->assertNotEmpty($owner->owner_code, 'Every owner receives a unique owner code.');

        // ...and manage them afterwards.
        $this->actingAs($officer)
            ->put(route('admin.owners.update', $owner), [
                'full_name' => 'Aisha Bello-Adamu',
                'phone' => '+234 803 111 9999',
                'email' => 'aisha@example.test',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('Aisha Bello-Adamu', $owner->fresh()->full_name);

        // FR-043 - owners are searchable by name, phone, email and shop number.
        $this->assertTrue(Owner::search('Bello-Adamu')->exists());
        $this->assertTrue(Owner::search('111 9999')->exists());
        $this->assertTrue(Owner::search('aisha@example.test')->exists());
    }

    /** 2. Shops can be created and allocated. */
    #[Test]
    public function shops_can_be_created_and_allocated(): void
    {
        $mall = $this->makeMall();
        $admin = $this->staff(Role::MALL_ADMIN);

        $this->actingAs($admin)->post(route('admin.shops.store'), [
            'mall_id' => $mall->id,
            'block_id' => $mall->blocks->first()->id,
            'shop_code' => 'BLK-A-GF01',
            'shop_number' => 'A-01',
            'shop_type' => 'retail',
            'size_sqm' => 36,
            'status' => Shop::AVAILABLE,
            'annual_rent' => 1200000,
            'service_charge' => 240000,
            'other_fees' => 60000,
        ])->assertRedirect();

        $shop = Shop::query()->where('shop_code', 'BLK-A-GF01')->firstOrFail();
        $owner = $this->makeOwner();

        $this->actingAs($admin)->post(route('admin.allocations.store'), [
            'shop_id' => $shop->id,
            'owner_id' => $owner->id,
            'allocation_date' => Carbon::today()->toDateString(),
            'allocation_type' => 'lease',
        ])->assertRedirect();

        $allocation = Allocation::query()->where('shop_id', $shop->id)->firstOrFail();

        $this->assertSame($owner->id, $allocation->owner_id);
        $this->assertSame(Allocation::DRAFT, $allocation->status);
        // FR-051 - a unique, formatted allocation reference is generated.
        $this->assertStringContainsString($mall->code, $allocation->reference_number);
        $this->assertStringContainsString('BLK-A', $allocation->reference_number);
        $this->assertSame(Shop::RESERVED, $shop->fresh()->status);
    }

    /** 3. Duplicate allocation is prevented (FR-032). */
    #[Test]
    public function duplicate_allocation_is_prevented(): void
    {
        $shop = $this->makeShop();
        $this->activeAllocation($shop, $this->makeOwner());

        $this->assertFalse($shop->fresh()->isAllocatable());

        // At the service layer the attempt raises a domain error...
        $this->expectException(AllocationException::class);
        app(AllocationService::class)->create($shop->fresh(), $this->makeOwner());
    }

    /** 3b. ...and the same refusal surfaces through the administration portal. */
    #[Test]
    public function duplicate_allocation_is_refused_in_the_portal(): void
    {
        $shop = $this->makeShop();
        $this->activeAllocation($shop, $this->makeOwner());

        $this->actingAs($this->staff(Role::MALL_ADMIN))
            ->post(route('admin.allocations.store'), [
                'shop_id' => $shop->id,
                'owner_id' => $this->makeOwner()->id,
                'allocation_date' => Carbon::today()->toDateString(),
                'allocation_type' => 'lease',
            ])
            ->assertSessionHasErrors('shop_id');

        $this->assertSame(1, Allocation::query()->where('shop_id', $shop->id)->count());
    }

    /** 4. Allocation letters can be generated (FR-060, FR-061). */
    #[Test]
    public function allocation_letters_can_be_generated(): void
    {
        $allocation = $this->activeAllocation();

        $letter = $allocation->documents()->where('type', AllocationDocument::LETTER)->first();
        $this->assertNotNull($letter, 'A letter is issued automatically on approval.');
        $this->assertNotEmpty($letter->document_number, 'FR-062 - the letter carries a unique verification reference.');

        $response = $this->actingAs($this->staff(Role::MALL_ADMIN))
            ->get(route('documents.letter', $allocation));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** 5. Allocation cards can be generated (FR-070). */
    #[Test]
    public function allocation_cards_can_be_generated(): void
    {
        $allocation = $this->activeAllocation();

        $card = $allocation->cards()->where('status', AllocationCard::ACTIVE)->first();
        $this->assertNotNull($card);

        $this->actingAs($this->staff(Role::MALL_ADMIN))
            ->get(route('documents.card', $allocation))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // FR-072 - a reissue retains the previous card and the reason.
        $replacement = app(AllocationService::class)
            ->reissueCard($allocation, 'Card reported lost', $this->staff(Role::MALL_ADMIN));

        $this->assertSame(AllocationCard::CANCELLED, $card->fresh()->status);
        $this->assertSame(AllocationCard::ACTIVE, $replacement->status);
        $this->assertSame($card->id, $replacement->reissue_of_id);
        $this->assertSame('Card reported lost', $replacement->reissue_reason);
    }

    /** 6. QR codes successfully verify allocations (FR-080 to FR-083). */
    #[Test]
    public function qr_codes_successfully_verify_allocations(): void
    {
        $allocation = $this->activeAllocation();

        // FR-081 - the public portal needs no login.
        $this->get(route('verify.show', $allocation->verification_token))
            ->assertOk()
            ->assertSee('VERIFIED')
            ->assertSee($allocation->reference_number)
            ->assertSee($allocation->owner->full_name);

        // FR-082 - an unknown code is reported as invalid.
        $this->get(route('verify.show', 'not-a-real-token'))
            ->assertOk()
            ->assertSee('INVALID OR UNRECOGNISED ALLOCATION');

        // FR-083 - a cancelled allocation reports its true current status.
        app(AllocationService::class)->cancel($allocation, 'Owner withdrew');

        $this->get(route('verify.show', $allocation->verification_token))
            ->assertOk()
            ->assertSee('CANCELLED')
            ->assertDontSee('&#10003;', false);

        // 7.2 - verification tokens are long and non-guessable.
        $this->assertSame(40, strlen($allocation->verification_token));
    }

    /** 7. Bills and invoices can be generated (FR-091, FR-092). */
    #[Test]
    public function bills_and_invoices_can_be_generated(): void
    {
        $allocation = $this->activeAllocation();
        $billing = app(BillingService::class);

        $invoice = $billing->generateInvoice($allocation, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertNotEmpty($invoice->invoice_number);
        $this->assertSame(3, $invoice->items()->count(), 'Rent, service charge and other fees are billed.');
        $this->assertSame('1500000.00', $invoice->total_amount);
        $this->assertSame('1500000.00', $invoice->balance);
        $this->assertSame(Invoice::UNPAID, $invoice->status);

        // FR-092 - a recurring run bills every active allocation and never double-bills.
        $this->activeAllocation();
        $first = $billing->runRecurringBilling(Carbon::parse('2027-01-01'));
        $second = $billing->runRecurringBilling(Carbon::parse('2027-01-01'));

        $this->assertCount(2, $first);
        $this->assertSame(
            $first->pluck('id')->sort()->values()->all(),
            $second->pluck('id')->sort()->values()->all(),
            'Re-running a billing period returns the same invoices rather than creating duplicates.',
        );
    }

    /** 7b. Overdue invoices are identified (FR-093, FR-094). */
    #[Test]
    public function outstanding_and_overdue_balances_are_calculated(): void
    {
        $allocation = $this->activeAllocation();
        $billing = app(BillingService::class);

        $invoice = $billing->generateInvoice(
            $allocation,
            Carbon::today()->subYear(),
            Carbon::today()->subDay(),
            null,
            Carbon::today()->subDays(10),
        );

        $this->assertSame('1500000.00', $allocation->owner->fresh()->outstandingBalance());

        $flagged = $billing->flagOverdueInvoices();

        $this->assertSame(1, $flagged);
        $this->assertSame(Invoice::OVERDUE, $invoice->fresh()->status);
        $this->assertTrue($invoice->fresh()->isOverdue());
    }

    /** 8. Payments can be recorded and reconciled (FR-100 to FR-103). */
    #[Test]
    public function payments_can_be_recorded_and_reconciled(): void
    {
        $allocation = $this->activeAllocation();
        $invoice = app(BillingService::class)->generateInvoice($allocation);
        $payments = app(PaymentService::class);

        // FR-102 - a partial payment leaves a balance.
        $payments->record($invoice, '500000.00', ['method' => Payment::BANK_TRANSFER]);
        $invoice->refresh();

        $this->assertSame('500000.00', $invoice->amount_paid);
        $this->assertSame('1000000.00', $invoice->balance);
        $this->assertSame(Invoice::PARTIALLY_PAID, $invoice->status);

        // Settling the remainder marks the invoice paid.
        $payments->record($invoice, '1000000.00', ['method' => Payment::POS]);
        $invoice->refresh();

        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame(Invoice::PAID, $invoice->status);

        // An invoice cannot be overpaid.
        $this->assertThrows(
            fn () => $payments->record($invoice->fresh(), '1.00'),
            BillingException::class,
        );
    }

    /** 8b. FR-103 - the same gateway settlement is never recorded twice. */
    #[Test]
    public function duplicate_gateway_payments_are_refused(): void
    {
        $invoice = app(BillingService::class)->generateInvoice($this->activeAllocation());
        $payments = app(PaymentService::class);

        $payments->record($invoice, '1000.00', ['method' => Payment::ONLINE, 'gateway_reference' => 'GW-ABC-123']);

        $this->assertThrows(
            fn () => $payments->record($invoice->fresh(), '1000.00', [
                'method' => Payment::ONLINE,
                'gateway_reference' => 'GW-ABC-123',
            ]),
            BillingException::class,
        );

        $this->assertSame(1, Payment::query()->where('gateway_reference', 'GW-ABC-123')->count());
    }

    /** 9. Receipts can be generated (FR-110, FR-111). */
    #[Test]
    public function receipts_can_be_generated(): void
    {
        $invoice = app(BillingService::class)->generateInvoice($this->activeAllocation());
        $payment = app(PaymentService::class)->record($invoice, '250000.00');

        $this->assertNotNull($payment->receipt, 'A receipt is issued for every confirmed payment.');
        $this->assertNotEmpty($payment->receipt->receipt_number);

        // FR-111 - the owner can download it.
        $this->actingAs($this->ownerUser($invoice->owner))
            ->get(route('documents.receipt', $payment->receipt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /** 10. Ownership transfers are properly recorded (FR-120 to FR-124). */
    #[Test]
    public function ownership_transfers_are_properly_recorded(): void
    {
        $shop = $this->makeShop();
        $allocation = $this->activeAllocation($shop, $this->makeOwner());
        $newOwner = $this->makeOwner();
        $approver = $this->staff(Role::MALL_ADMIN);
        $transfers = app(TransferService::class);

        $transfer = $transfers->request($allocation, $newOwner, 'Business sold');
        $this->assertSame(OwnershipTransfer::PENDING, $transfer->status);

        // FR-121 - an outstanding balance blocks approval.
        app(BillingService::class)->generateInvoice($allocation);

        $this->assertThrows(
            fn () => $transfers->approve($transfer->fresh(), $approver),
            TransferException::class,
        );
        $this->assertSame(OwnershipTransfer::PENDING, $transfer->fresh()->status);

        // Once settled, the transfer completes.
        $invoice = $allocation->invoices()->first();
        app(PaymentService::class)->record($invoice, $invoice->balance);

        $transfer = $transfers->approve($transfer->fresh(), $approver);

        $this->assertSame(OwnershipTransfer::APPROVED, $transfer->status);

        // FR-123 - the old allocation is closed, never deleted.
        $this->assertSame(Allocation::TRANSFERRED, $allocation->fresh()->status);
        $this->assertDatabaseHas('allocations', ['id' => $allocation->id]);

        // FR-124 - a new allocation, letter, card and QR record are issued.
        $new = $transfer->newAllocation;
        $this->assertNotNull($new);
        $this->assertSame($newOwner->id, $new->owner_id);
        $this->assertSame(Allocation::ACTIVE, $new->status);
        $this->assertSame($allocation->id, $new->previous_allocation_id);
        $this->assertNotSame($allocation->verification_token, $new->verification_token);
        $this->assertTrue($new->documents()->where('type', AllocationDocument::LETTER)->exists());
        $this->assertTrue($new->cards()->where('status', AllocationCard::ACTIVE)->exists());

        // FR-123 - the shop retains its complete ownership history.
        $history = $transfers->ownershipHistory($shop->fresh());
        $this->assertCount(2, $history);
    }

    /** 11. Owners can access their portal (SRS 3.2, FR-150 to FR-152). */
    #[Test]
    public function owners_can_access_their_portal(): void
    {
        $allocation = $this->activeAllocation();
        $owner = $allocation->owner;
        $invoice = app(BillingService::class)->generateInvoice($allocation);
        $user = $this->ownerUser($owner);

        $this->actingAs($user)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee($owner->full_name)
            ->assertSee($allocation->reference_number);

        $this->actingAs($user)->get(route('portal.invoices.show', $invoice))->assertOk();
        $this->actingAs($user)->get(route('documents.letter', $allocation))->assertOk();

        // FR-152 - the owner updates permitted details themselves.
        $this->actingAs($user)->put(route('portal.profile.update'), [
            'phone' => '+234 900 000 0001',
            'email' => $owner->email,
            'address' => '5 New Street',
        ])->assertRedirect();

        $this->assertSame('+234 900 000 0001', $owner->fresh()->phone);

        // An owner sees only their own records.
        $otherInvoice = app(BillingService::class)->generateInvoice($this->activeAllocation());
        $this->actingAs($user)->get(route('portal.invoices.show', $otherInvoice))->assertForbidden();

        // ...and cannot reach the administration portal.
        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('portal.dashboard'));
    }

    /** 12. Notifications can be delivered (FR-130 to FR-133). */
    #[Test]
    public function notifications_can_be_delivered(): void
    {
        $allocation = $this->activeAllocation();
        $owner = $allocation->owner;

        // FR-133 - activation notifies the owner automatically.
        $this->assertTrue(
            Notification::query()->where('event', 'allocation.activated')->exists(),
            'Activating an allocation notifies the owner.',
        );

        // FR-133 - so does raising an invoice and receiving a payment.
        $invoice = app(BillingService::class)->generateInvoice($allocation);
        app(PaymentService::class)->record($invoice, '100.00');

        $this->assertTrue(Notification::query()->where('event', 'invoice.generated')->exists());
        $this->assertTrue(Notification::query()->where('event', 'payment.received')->exists());

        // FR-130 / FR-131 - a broadcast reaches every active owner.
        $this->makeOwner();
        $broadcast = app(NotificationService::class)->createAndSend(
            'Mall closure notice',
            'The mall will close early on Friday.',
            ['channel' => Notification::IN_SYSTEM, 'target_type' => Notification::TARGET_ALL],
        );

        $this->assertSame(Owner::query()->where('status', 'active')->count(), $broadcast->recipients()->count());
        $this->assertSame('sent', $broadcast->status);

        // FR-131 - owners carrying a balance can be targeted specifically.
        $targeted = app(NotificationService::class)->createAndSend(
            'Payment reminder',
            'Please settle your outstanding balance.',
            ['target_type' => Notification::TARGET_OUTSTANDING],
        );

        $this->assertSame(1, $targeted->recipients()->count());
        $this->assertSame($owner->id, $targeted->recipients()->first()->owner_id);

        // The owner sees them in their portal.
        $this->actingAs($this->ownerUser($owner))
            ->get(route('portal.notifications.index'))
            ->assertOk()
            ->assertSee('Mall closure notice');
    }

    /** 13. Support tickets can be created and managed (FR-140 to FR-143). */
    #[Test]
    public function support_tickets_can_be_created_and_managed(): void
    {
        $allocation = $this->activeAllocation();
        $owner = $allocation->owner;
        $ownerUser = $this->ownerUser($owner);
        $officer = $this->staff(Role::SUPPORT_OFFICER);

        $this->actingAs($ownerUser)->post(route('portal.tickets.store'), [
            'category' => 'electricity',
            'subject' => 'No power supply',
            'description' => 'The shop has had no power since yesterday.',
            'priority' => 'high',
            'shop_id' => $allocation->shop_id,
        ])->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertNotEmpty($ticket->ticket_number);
        $this->assertSame(SupportTicket::OPEN, $ticket->status);
        $this->assertSame(1, $ticket->messages()->count(), 'The description opens the conversation.');

        // FR-140 / FR-143 - staff assign, reply and move the ticket along.
        $this->actingAs($officer)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $officer->id,
            'assigned_department' => 'Facilities',
        ])->assertRedirect();

        $this->actingAs($officer)->post(route('admin.tickets.reply', $ticket), [
            'body' => 'A technician has been dispatched.',
        ])->assertRedirect();

        $this->actingAs($officer)->post(route('admin.tickets.reply', $ticket), [
            'body' => 'Meter fault confirmed - internal only.',
            'is_internal' => '1',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($officer->id, $ticket->assigned_to);
        $this->assertSame(SupportTicket::IN_PROGRESS, $ticket->status);
        $this->assertSame(3, $ticket->messages()->count());

        // Internal notes stay out of the owner's view.
        $this->actingAs($ownerUser)->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('A technician has been dispatched.')
            ->assertDontSee('internal only');

        // FR-142 - the lifecycle completes.
        $this->actingAs($officer)->post(route('admin.tickets.status', $ticket), [
            'status' => SupportTicket::RESOLVED,
        ])->assertRedirect();

        $this->assertSame(SupportTicket::RESOLVED, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    /** 14. Reports can be generated (FR-160 to FR-163). */
    #[Test]
    public function reports_can_be_generated(): void
    {
        $allocation = $this->activeAllocation();
        $invoice = app(BillingService::class)->generateInvoice($allocation);
        app(PaymentService::class)->record($invoice, '500000.00');

        $reports = app(ReportService::class);

        $allocationReport = $reports->allocationReport();
        $this->assertSame(1, $allocationReport['total_shops']);
        $this->assertSame(1, $allocationReport['allocated_shops']);
        $this->assertSame(0, $allocationReport['unallocated_shops']);
        $this->assertSame(100.0, $allocationReport['occupancy_rate']);

        $financial = $reports->financialReport();
        $this->assertSame('1500000.00', $financial['expected_revenue']);
        $this->assertSame('500000.00', $financial['revenue_collected']);
        $this->assertSame('1000000.00', $financial['outstanding_balance']);
        $this->assertNotEmpty($financial['revenue_by_category']);

        $owners = $reports->ownerReport();
        $this->assertSame(1, $owners['total_owners']);
        $this->assertSame(1, $owners['owners_with_outstanding']);
        $this->assertCount(1, $reports->ownersWithOutstanding());

        app(SupportTicketService::class)->open($allocation->owner, [
            'category' => 'water', 'subject' => 'Leak', 'description' => 'Water leak in the shop.',
        ]);
        $this->assertSame(1, $reports->supportReport()['open_tickets']);

        // The reporting pages and CSV exports are reachable.
        $auditor = $this->staff(Role::AUDITOR);
        foreach (['allocations', 'financial', 'owners', 'support'] as $report) {
            $this->actingAs($auditor)->get(route("admin.reports.{$report}"))->assertOk();
            $this->actingAs($auditor)->get(route('admin.reports.export', $report))->assertOk();
        }
    }

    /** 15. All critical actions are recorded in the audit trail (FR-170, FR-171). */
    #[Test]
    public function critical_actions_are_recorded_in_the_audit_trail(): void
    {
        $officer = $this->staff(Role::ALLOCATION_OFFICER);
        $owner = $this->makeOwner();

        $this->actingAs($officer);

        $allocation = $this->activeAllocation(null, $owner, $this->staff(Role::MALL_ADMIN));
        $invoice = app(BillingService::class)->generateInvoice($allocation);
        app(PaymentService::class)->record($invoice, '1000.00');

        foreach (['created.allocation', 'updated.allocation', 'created.invoice', 'created.payment', 'card.issued'] as $action) {
            $this->assertTrue(
                AuditLog::query()->where('action', $action)->exists(),
                "The audit trail records \"{$action}\".",
            );
        }

        // FR-170 - the entry carries the user, the record and the values either side of the change.
        $statusChange = AuditLog::query()
            ->where('action', 'updated.allocation')
            ->whereNotNull('new_values')
            ->get()
            ->first(fn (AuditLog $log) => array_key_exists('status', $log->new_values ?? []));

        $this->assertNotNull($statusChange);
        $this->assertArrayHasKey('status', $statusChange->old_values);
        $this->assertNotSame($statusChange->old_values['status'], $statusChange->new_values['status']);
        $this->assertNotNull($statusChange->user_id);

        // FR-171 - audit records cannot be edited or removed.
        $this->assertThrows(fn () => $statusChange->update(['action' => 'tampered']), \RuntimeException::class);
        $this->assertThrows(fn () => $statusChange->delete(), \RuntimeException::class);
    }

    /** 15b. Password hashes never reach the audit trail. */
    #[Test]
    public function audit_trail_excludes_secrets(): void
    {
        $user = $this->staff(Role::FINANCE_OFFICER);
        $user->update(['password' => 'a-new-secret-password']);

        $entries = AuditLog::query()->where('auditable_type', User::class)->get();

        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $this->assertArrayNotHasKey('password', $entry->new_values ?? []);
            $this->assertArrayNotHasKey('password', $entry->old_values ?? []);
        }
    }

    /** 16. User permissions restrict unauthorised access (FR-011). */
    #[Test]
    public function user_permissions_restrict_unauthorised_access(): void
    {
        // A support officer has no business in billing or the audit trail.
        $support = $this->staff(Role::SUPPORT_OFFICER);
        $this->actingAs($support)->get(route('admin.invoices.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.allocations.create'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.tickets.index'))->assertOk();

        // An allocation officer allocates but does not handle money.
        $allocationOfficer = $this->staff(Role::ALLOCATION_OFFICER);
        $this->actingAs($allocationOfficer)->get(route('admin.allocations.create'))->assertOk();
        $this->actingAs($allocationOfficer)->get(route('admin.invoices.index'))->assertForbidden();

        // A finance officer handles money but does not allocate.
        $finance = $this->staff(Role::FINANCE_OFFICER);
        $this->actingAs($finance)->get(route('admin.invoices.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.allocations.create'))->assertForbidden();

        // The auditor reads everything and changes nothing (SRS 5.6).
        $auditor = $this->staff(Role::AUDITOR);
        $this->actingAs($auditor)->get(route('admin.audit.index'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.allocations.index'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.invoices.index'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.allocations.create'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.invoices.create'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.users.create'))->assertForbidden();

        // The super administrator reaches everything.
        $super = $this->staff(Role::SUPER_ADMIN);
        foreach ([Permissions::INVOICES_GENERATE, Permissions::ROLES_MANAGE, Permissions::AUDIT_VIEW] as $permission) {
            $this->assertTrue($super->hasPermission($permission));
        }

        // Signing out is required to reach anything at all.
        $this->post(route('logout'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    /** Money handling is exact to the cent across the billing chain. */
    #[Test]
    public function invoice_arithmetic_is_exact(): void
    {
        $allocation = $this->activeAllocation(
            $this->makeShop(null, ['annual_rent' => '0.10', 'service_charge' => '0.20', 'other_fees' => '0']),
        );

        $invoice = app(BillingService::class)->generateInvoice($allocation);

        $this->assertSame('0.30', $invoice->total_amount);

        app(PaymentService::class)->record($invoice, '0.10');
        $this->assertSame('0.20', $invoice->fresh()->balance);

        app(PaymentService::class)->record($invoice->fresh(), '0.20');
        $this->assertSame('0.00', $invoice->fresh()->balance);
        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);
        $this->assertSame(0, Money::compare($invoice->fresh()->balance, '0'));
    }
}
