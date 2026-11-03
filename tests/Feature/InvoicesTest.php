<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('invoices'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_invoices_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('invoices'));

        $response->assertOk();
        $response->assertSee('Invoices');
        $response->assertSee('New invoice');
        $response->assertSee(route('invoices.create'), false);
    }

    public function test_guests_are_redirected_from_the_create_invoice_page(): void
    {
        $response = $this->get(route('invoices.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_create_invoice_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('New invoice');
        $response->assertSee('Search customers by company or email');
        $response->assertSee('Invoice identifier');
        $response->assertSee('Issue date');
        $response->assertSee('Due date');
        $response->assertSee('Tax Registration #');
        $response->assertSee('Line items');
        $response->assertSee('Notes');
    }

    public function test_the_invoices_page_lists_invoices_with_pdf_and_view_links(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['invoice_number' => 'INV-TEST-1']);

        $response = $this->get(route('invoices'));

        $response->assertOk();
        $response->assertSee('INV-TEST-1');
        $response->assertSee(route('invoices.show', $invoice), false);
        $response->assertSee(route('invoices.pdf', $invoice), false);
        $response->assertSee(route('invoices.pdf.download', $invoice), false);
        $response->assertSee(route('invoices.edit', $invoice), false);
    }

    public function test_the_invoice_show_page_displays_the_invoice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-TEST-2',
            'description' => 'Website redesign',
            'tax_number' => 'GST-123',
        ]);

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('INV-TEST-2');
        $response->assertSee('Website redesign');
        $response->assertSee('Tax Registration #');
        $response->assertSee($invoice->customer->company_name);
        $response->assertSee(route('invoices.pdf', $invoice), false);
        $response->assertSee(route('invoices.pdf.download', $invoice), false);
    }

    public function test_an_invoice_can_be_marked_as_paid(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create(['status' => 'sent', 'total' => 1000]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_date', now()->toDateString())
            ->set('payment_method', 'bank_transfer')
            ->set('payment_reference', 'REF-123')
            ->set('payment_note', 'Paid in full')
            ->call('markAsPaid')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertSame('bank_transfer', $invoice->payment_method);
        $this->assertSame('REF-123', $invoice->payment_reference);
        $this->assertSame('Paid in full', $invoice->payment_note);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_marking_an_invoice_as_paid_requires_a_payment_method(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create(['status' => 'sent']);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_method', '')
            ->call('markAsPaid')
            ->assertHasErrors(['payment_method' => 'required']);

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_an_invoice_can_be_deleted_after_all_payments_are_removed(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create(['status' => 'sent', 'total' => 1000]);

        $payment = $invoice->payments()->create([
            'amount' => 400,
            'paid_at' => now()->toDateString(),
            'method' => 'bank_transfer',
        ]);

        $invoice->refreshPaymentState();

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('deleteInvoice');

        $this->assertNotSoftDeleted($invoice);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice->fresh()])
            ->call('removePayment', $payment->id)
            ->call('deleteInvoice')
            ->assertRedirect(route('invoices'));

        $this->assertSoftDeleted($invoice);
    }

    public function test_partial_payments_can_be_applied_and_completed(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create(['status' => 'sent', 'total' => 1000]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_amount', '400')
            ->set('payment_method', 'bank_transfer')
            ->call('markAsPaid')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('sent', $invoice->status);
        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(40.0, $invoice->paidPercentage());
        $this->assertSame(600.0, $invoice->balanceDue());
        $this->assertTrue($invoice->isPartiallyPaid());

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertSet('payment_amount', '600.00')
            ->set('payment_amount', '600')
            ->set('payment_method', 'cash')
            ->call('markAsPaid')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(100.0, $invoice->paidPercentage());
        $this->assertFalse($invoice->isPartiallyPaid());
        $this->assertSame(2, $invoice->payments()->count());
    }

    public function test_payment_proof_can_be_uploaded_and_removed(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create(['status' => 'sent', 'total' => 1000]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_amount', '400')
            ->set('payment_method', 'bank_transfer')
            ->set('payment_proof', UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'))
            ->call('markAsPaid')
            ->assertHasNoErrors();

        $payment = $invoice->payments()->firstOrFail();

        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);

        $this->get(route('payments.proof', $payment))->assertOk();

        Livewire::test('pages::invoices.show', ['invoice' => $invoice->fresh()])
            ->call('removePayment', $payment->id);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        Storage::disk('local')->assertMissing($payment->proof_path);
    }

    public function test_the_invoice_list_shows_the_paid_percentage(): void
    {
        $this->actingAs(User::factory()->create());

        Invoice::factory()->create([
            'invoice_number' => 'PARTIAL-LIST',
            'status' => 'sent',
            'total' => 1000,
            'amount_paid' => 400,
        ]);

        $this->get(route('invoices'))
            ->assertOk()
            ->assertSee('PARTIAL-LIST')
            ->assertSee('40%')
            ->assertSee('Partial');
    }

    public function test_the_invoice_list_can_filter_partially_paid_invoices(): void
    {
        $this->actingAs(User::factory()->create());

        Invoice::factory()->create([
            'invoice_number' => 'PARTIAL-FILTER',
            'status' => 'sent',
            'total' => 1000,
            'amount_paid' => 400,
        ]);

        Invoice::factory()->create([
            'invoice_number' => 'UNPAID-FILTER',
            'status' => 'sent',
            'total' => 1000,
            'amount_paid' => 0,
        ]);

        Livewire::test('pages::invoices')
            ->set('status', 'partial')
            ->assertSee('PARTIAL-FILTER')
            ->assertDontSee('UNPAID-FILTER');
    }

    public function test_a_draft_invoice_can_be_marked_as_sent(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create(['status' => 'draft']);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('markAsSent')
            ->assertHasNoErrors();

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_invoices_can_be_searched_by_identifier_customer_or_description(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'ACME-100',
            'description' => 'Website build',
        ]);

        Invoice::factory()->create([
            'invoice_number' => 'OTHER-200',
            'description' => 'Support retainer',
        ]);

        Livewire::test('pages::invoices')
            ->set('search', 'ACME')
            ->assertSee('ACME-100')
            ->assertDontSee('OTHER-200')
            ->set('search', 'Website')
            ->assertSee('ACME-100')
            ->assertDontSee('OTHER-200')
            ->set('search', 'Acme Corp')
            ->assertSee('ACME-100')
            ->assertDontSee('OTHER-200');
    }

    public function test_invoices_can_be_filtered_by_status(): void
    {
        Invoice::factory()->create(['status' => 'draft', 'invoice_number' => 'DRAFT-1']);
        Invoice::factory()->create(['status' => 'sent', 'invoice_number' => 'SENT-1']);

        Livewire::test('pages::invoices')
            ->set('status', 'sent')
            ->assertSee('SENT-1')
            ->assertDontSee('DRAFT-1');
    }

    public function test_invoices_can_be_filtered_by_customer(): void
    {
        $customerA = Customer::factory()->create(['company_name' => 'Alpha']);
        $customerB = Customer::factory()->create(['company_name' => 'Beta']);

        Invoice::factory()->create(['customer_id' => $customerA->id, 'invoice_number' => 'ALPHA-1']);
        Invoice::factory()->create(['customer_id' => $customerB->id, 'invoice_number' => 'BETA-1']);

        Livewire::test('pages::invoices')
            ->set('customer_id', (string) $customerA->id)
            ->assertSee('ALPHA-1')
            ->assertDontSee('BETA-1');
    }

    public function test_invoices_can_be_filtered_by_issue_date_range(): void
    {
        Invoice::factory()->create(['invoice_number' => 'OLD-1', 'issue_date' => '2025-01-01']);
        Invoice::factory()->create(['invoice_number' => 'NEW-1', 'issue_date' => '2026-06-01']);

        Livewire::test('pages::invoices')
            ->set('from', '2026-01-01')
            ->set('to', '2026-12-31')
            ->assertSee('NEW-1')
            ->assertDontSee('OLD-1');
    }

    public function test_the_number_of_invoice_rows_per_page_can_be_changed(): void
    {
        Invoice::factory()
            ->count(15)
            ->sequence(fn ($sequence) => ['invoice_number' => 'INV-'.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT)])
            ->create();

        Livewire::test('pages::invoices')
            ->assertDontSee('INV-01')
            ->set('perPage', '25')
            ->assertSee('INV-01');
    }

    public function test_filters_can_be_cleared(): void
    {
        Invoice::factory()->create(['status' => 'draft', 'invoice_number' => 'ACME-1']);
        Invoice::factory()->create(['status' => 'sent', 'invoice_number' => 'OTHER-1']);

        Livewire::test('pages::invoices')
            ->set('search', 'ACME')
            ->set('status', 'draft')
            ->assertSee('ACME-1')
            ->assertDontSee('OTHER-1')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('status', '')
            ->assertSee('ACME-1')
            ->assertSee('OTHER-1');
    }

    public function test_an_unpaid_invoice_can_be_soft_deleted_from_the_invoice_page(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create([
            'status' => 'sent',
            'paid_at' => null,
        ]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('deleteInvoice')
            ->assertRedirect(route('invoices'));

        $this->assertSoftDeleted($invoice);
    }

    public function test_a_paid_invoice_cannot_be_soft_deleted(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('deleteInvoice');

        $this->assertNotSoftDeleted($invoice);
    }

    public function test_an_unpaid_invoice_can_be_soft_deleted_from_the_invoice_list(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create([
            'status' => 'draft',
            'paid_at' => null,
        ]);

        Livewire::test('pages::invoices')
            ->call('deleteInvoice', $invoice->id);

        $this->assertSoftDeleted($invoice);
    }

    public function test_soft_deleted_invoices_are_hidden_from_the_invoice_list(): void
    {
        $this->actingAs(User::factory()->create());

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'DELETED-INVOICE',
            'status' => 'draft',
        ]);

        $invoice->delete();

        $this->get(route('invoices'))
            ->assertOk()
            ->assertDontSee('DELETED-INVOICE');
    }
}
