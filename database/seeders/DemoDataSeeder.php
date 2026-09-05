<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Mall;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AllocationService;
use App\Services\BillingService;
use App\Services\PaymentService;
use App\Services\ReferenceGenerator;
use App\Services\SupportTicketService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * A worked demonstration data set: one mall, staff accounts for every role,
 * shops across two blocks, registered owners, approved allocations with
 * documents, invoices, payments and a support ticket.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly AllocationService $allocations,
        private readonly BillingService $billing,
        private readonly PaymentService $payments,
        private readonly SupportTicketService $tickets,
    ) {}

    public function run(): void
    {
        $mall = $this->createMall();
        $staff = $this->createStaff();
        $shops = $this->createShops($mall);
        $owners = $this->createOwners();

        $this->createAllocations($shops, $owners, $staff);
        $this->createBillingAndPayments($staff);
        $this->createTicket($owners->first());
    }

    private function createMall(): Mall
    {
        $mall = Mall::updateOrCreate(
            ['code' => 'GMP'],
            [
                'name' => 'Gondal Mega Plaza',
                'address' => '1 Ring Road, Central Business District',
                'description' => 'A three-block shopping mall with 60 lettable commercial units.',
                'phone' => '+234 800 000 0000',
                'email' => 'management@gondalmegaplaza.example',
                'status' => 'active',
            ],
        );

        foreach ([['BLK-A', 'Block A'], ['BLK-B', 'Block B']] as [$code, $name]) {
            $block = Block::updateOrCreate(
                ['mall_id' => $mall->id, 'code' => $code],
                ['name' => $name, 'status' => 'active'],
            );

            foreach ([['GF', 'Ground Floor', 0], ['1F', 'First Floor', 1]] as [$fCode, $fName, $level]) {
                Floor::updateOrCreate(
                    ['block_id' => $block->id, 'code' => $fCode],
                    ['name' => $fName, 'level' => $level],
                );
            }
        }

        return $mall;
    }

    /** @return array<string, User> */
    private function createStaff(): array
    {
        $accounts = [
            [Role::SUPER_ADMIN, 'System Administrator', 'admin@mall.test'],
            [Role::MALL_ADMIN, 'Mall Administrator', 'mall.admin@mall.test'],
            [Role::ALLOCATION_OFFICER, 'Allocation Officer', 'allocation@mall.test'],
            [Role::FINANCE_OFFICER, 'Finance Officer', 'finance@mall.test'],
            [Role::SUPPORT_OFFICER, 'Support Officer', 'support@mall.test'],
            [Role::AUDITOR, 'Internal Auditor', 'auditor@mall.test'],
        ];

        $staff = [];

        foreach ($accounts as [$roleSlug, $name, $email]) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

            $staff[$roleSlug] = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(config('mall.demo_password')),
                    'role_id' => $role->id,
                    'is_active' => true,
                    'email_verified_at' => Carbon::now(),
                ],
            );
        }

        return $staff;
    }

    /** @return Collection<int, Shop> */
    private function createShops(Mall $mall)
    {
        $shops = collect();

        foreach ($mall->blocks()->with('floors')->get() as $block) {
            foreach ($block->floors as $floor) {
                for ($i = 1; $i <= 8; $i++) {
                    $number = sprintf('%s-%s%02d', $block->code, $floor->code, $i);

                    $shops->push(Shop::updateOrCreate(
                        ['shop_code' => $number],
                        [
                            'mall_id' => $mall->id,
                            'block_id' => $block->id,
                            'floor_id' => $floor->id,
                            'shop_number' => $number,
                            'shop_type' => $i % 4 === 0 ? 'anchor' : 'retail',
                            'size_sqm' => $i % 4 === 0 ? 120 : 36,
                            'status' => Shop::AVAILABLE,
                            'annual_rent' => $i % 4 === 0 ? 4800000 : 1200000,
                            'service_charge' => $i % 4 === 0 ? 720000 : 240000,
                            'other_fees' => 60000,
                        ],
                    ));
                }
            }
        }

        return $shops;
    }

    /** @return Collection<int, Owner> */
    private function createOwners()
    {
        $people = [
            ['Aisha Bello', 'female', '+234 803 111 1111', 'aisha.bello@example.test', 'Bello Fabrics Ltd', 'Textiles'],
            ['Chinedu Okafor', 'male', '+234 803 222 2222', 'chinedu.okafor@example.test', 'Okafor Electronics', 'Consumer electronics'],
            ['Fatima Sani', 'female', '+234 803 333 3333', 'fatima.sani@example.test', 'Sani Pharmacy', 'Pharmacy'],
            ['Emeka Nwosu', 'male', '+234 803 444 4444', 'emeka.nwosu@example.test', 'Nwosu Foods', 'Restaurant'],
            ['Halima Yusuf', 'female', '+234 803 555 5555', 'halima.yusuf@example.test', 'Yusuf Beauty', 'Cosmetics'],
        ];

        $ownerRole = Role::query()->where('slug', Role::SHOP_OWNER)->firstOrFail();
        $owners = collect();

        foreach ($people as $index => [$name, $gender, $phone, $email, $business, $nature]) {
            $owner = Owner::updateOrCreate(
                ['email' => $email],
                [
                    'owner_code' => $this->references->ownerCode(),
                    'full_name' => $name,
                    'gender' => $gender,
                    'date_of_birth' => Carbon::parse('1985-01-01')->addMonths($index * 7),
                    'nationality' => 'Nigerian',
                    'phone' => $phone,
                    'address' => sprintf('%d Market Road, Central District', 10 + $index),
                    'id_type' => 'national_id',
                    'id_number' => sprintf('NIN%09d', 100000 + $index),
                    'nok_name' => 'Next of Kin '.($index + 1),
                    'nok_relationship' => 'Sibling',
                    'nok_phone' => '+234 805 000 000'.$index,
                    'nok_address' => 'Central District',
                    'business_name' => $business,
                    'business_reg_number' => sprintf('RC%07d', 200000 + $index),
                    'business_address' => 'Gondal Mega Plaza',
                    'business_nature' => $nature,
                    'status' => 'active',
                ],
            );

            // SRS 3.2 - a portal account for the owner.
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(config('mall.demo_password')),
                    'role_id' => $ownerRole->id,
                    'owner_id' => $owner->id,
                    'phone' => $phone,
                    'is_active' => true,
                    'email_verified_at' => Carbon::now(),
                ],
            );

            $owners->push($owner);
        }

        return $owners;
    }

    /** @param  array<string, User>  $staff */
    private function createAllocations($shops, $owners, array $staff): void
    {
        $approver = $staff[Role::MALL_ADMIN];
        $available = $shops->where('status', Shop::AVAILABLE)->values();

        foreach ($owners as $index => $owner) {
            $shop = $available->get($index);

            if (! $shop || ! $shop->fresh()->isAllocatable()) {
                continue;
            }

            $allocation = $this->allocations->create($shop, $owner, [
                'allocation_date' => Carbon::today()->subMonths(6 - $index),
                'expiry_date' => Carbon::today()->addYears(2),
                'allocation_type' => 'lease',
            ]);

            $this->allocations->submitForApproval($allocation);

            // Walk every configured approval stage (FR-053).
            foreach ($this->allocations->approvalStages() as $stage) {
                $allocation = $this->allocations->approve($allocation->fresh(), $approver, "Approved at {$stage}.");
            }

            // Leave the last owner's allocation approved but not yet active, so
            // the demo data covers both states.
            if ($index < $owners->count() - 1) {
                $this->allocations->activate($allocation->fresh());
            }
        }
    }

    /** @param  array<string, User>  $staff */
    private function createBillingAndPayments(array $staff): void
    {
        $finance = $staff[Role::FINANCE_OFFICER];
        $periodStart = Carbon::today()->startOfYear();

        $invoices = $this->billing->runRecurringBilling($periodStart);

        foreach ($invoices->values() as $index => $invoice) {
            $invoice = $invoice->fresh();

            if (Money::isSettled($invoice->balance)) {
                continue;
            }

            // A spread of settled, part-settled and untouched invoices.
            $portion = match ($index % 3) {
                0 => (string) $invoice->balance,                          // paid in full
                1 => Money::divide($invoice->balance, 2),                 // FR-102 partial payment
                default => null,                                          // left outstanding
            };

            if ($portion === null) {
                continue;
            }

            $this->payments->record($invoice, $portion, [
                'method' => $index % 2 === 0 ? Payment::BANK_TRANSFER : Payment::POS,
                'payment_date' => Carbon::today()->subDays(20 - $index),
                'received_by' => $finance->id,
                'notes' => 'Demonstration payment.',
            ]);
        }

        // An overdue invoice for the reporting and notification demonstrations (FR-094).
        $allocation = Allocation::query()->where('status', Allocation::ACTIVE)->first();

        if ($allocation) {
            $lastYear = (clone $periodStart)->subYear();

            $this->billing->generateInvoice(
                $allocation,
                $lastYear,
                (clone $lastYear)->addYear()->subDay(),
                null,
                Carbon::today()->subDays(45),
            );

            // Runs the overdue cycle so the demo data carries the reminder too.
            $this->billing->flagOverdueInvoices();
        }
    }

    private function createTicket(?Owner $owner): void
    {
        if (! $owner) {
            return;
        }

        $existing = SupportTicket::query()->where('owner_id', $owner->id)->exists();

        if ($existing) {
            return;
        }

        $ticket = $this->tickets->open($owner, [
            'category' => 'electricity',
            'subject' => 'Power outage affecting the shop',
            'description' => 'The shop has had no power supply since yesterday evening. Kindly attend to it.',
            'priority' => 'high',
        ]);

        $officer = User::query()->whereHas('role', fn ($q) => $q->where('slug', Role::SUPPORT_OFFICER))->first();

        if ($officer) {
            $this->tickets->assign($ticket, $officer, 'Facilities');
            $this->tickets->reply($ticket->fresh(), 'A technician has been dispatched to inspect the distribution board.', $officer);
        }
    }
}
