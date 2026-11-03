<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $invoice = Invoice::factory()->create(['status' => 'sent']);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('markAsPaid')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
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
}
