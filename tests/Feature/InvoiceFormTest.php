<?php

namespace Tests\Feature;

use App\Models\BillingCategory;
use App\Models\Invoice;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-091 - the invoice form posts a fixed set of fee rows, most of which a
 * user typically leaves blank.
 */
class InvoiceFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    #[Test]
    public function blank_fee_rows_are_ignored(): void
    {
        $allocation = $this->activeAllocation();
        $rent = BillingCategory::query()->where('code', BillingCategory::ANNUAL_RENT)->firstOrFail();

        $this->actingAs($this->staff(Role::FINANCE_OFFICER))
            ->post(route('admin.invoices.store'), [
                'allocation_id' => $allocation->id,
                'billing_period_start' => '2027-01-01',
                'billing_period_end' => '2027-12-31',
                'due_date' => '2027-01-31',
                'items' => [
                    ['billing_category_id' => $rent->id, 'description' => 'Annual rent 2027', 'quantity' => 1, 'unit_amount' => 1200000],
                    // The spare rows the form renders, left untouched.
                    ['billing_category_id' => '', 'description' => '', 'quantity' => 1, 'unit_amount' => ''],
                    ['billing_category_id' => '', 'description' => '', 'quantity' => 1, 'unit_amount' => ''],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = Invoice::query()->latest('id')->firstOrFail();

        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('1200000.00', $invoice->total_amount);
        $this->assertSame($rent->id, $invoice->items()->value('billing_category_id'));
    }

    #[Test]
    public function a_fee_row_without_a_category_is_accepted(): void
    {
        $allocation = $this->activeAllocation();

        $this->actingAs($this->staff(Role::FINANCE_OFFICER))
            ->post(route('admin.invoices.store'), [
                'allocation_id' => $allocation->id,
                'billing_period_start' => '2027-01-01',
                'billing_period_end' => '2027-12-31',
                'due_date' => '2027-01-31',
                // No billing_category_id key at all.
                'items' => [
                    ['description' => 'Ad-hoc repair contribution', 'quantity' => 2, 'unit_amount' => '150.55'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = Invoice::query()->latest('id')->firstOrFail();

        $this->assertSame('301.10', $invoice->total_amount);
        $this->assertNull($invoice->items()->value('billing_category_id'));
    }

    #[Test]
    public function an_invoice_needs_at_least_one_fee_row(): void
    {
        $allocation = $this->activeAllocation();

        $this->actingAs($this->staff(Role::FINANCE_OFFICER))
            ->post(route('admin.invoices.store'), [
                'allocation_id' => $allocation->id,
                'billing_period_start' => '2027-01-01',
                'billing_period_end' => '2027-12-31',
                'due_date' => '2027-01-31',
                'items' => [
                    ['billing_category_id' => '', 'description' => '', 'quantity' => 1, 'unit_amount' => ''],
                ],
            ])
            ->assertSessionHasErrors('items');
    }

    #[Test]
    public function a_partly_filled_fee_row_is_rejected(): void
    {
        $allocation = $this->activeAllocation();

        $this->actingAs($this->staff(Role::FINANCE_OFFICER))
            ->post(route('admin.invoices.store'), [
                'allocation_id' => $allocation->id,
                'billing_period_start' => '2027-01-01',
                'billing_period_end' => '2027-12-31',
                'due_date' => '2027-01-31',
                'items' => [
                    // An amount with no description: the user started this row and stopped.
                    ['billing_category_id' => '', 'description' => '', 'quantity' => 1, 'unit_amount' => '5000'],
                ],
            ])
            ->assertSessionHasErrors('items.0.description');
    }
}
