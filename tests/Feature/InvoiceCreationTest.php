<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_create_invoice_page(): void
    {
        $response = $this->get(route('invoices.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_create_invoice_page_uses_the_configured_tax_defaults(): void
    {
        Setting::current()->update([
            'tax_rate' => '15',
            'tax_number' => 'GST-999',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('wire:model.live.debounce.750ms="customer_search"', false);
        $response->assertSee('wire:target="customer_search"', false);

        Livewire::test('pages::invoices.create')
            ->assertSet('tax_rate', '15.00')
            ->assertSet('tax_number', 'GST-999');
    }

    public function test_customer_search_results_are_limited_to_ten(): void
    {
        Customer::factory()
            ->count(12)
            ->sequence(fn ($sequence) => ['company_name' => 'Alpha '.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT)])
            ->create();

        Livewire::test('pages::invoices.create')
            ->set('customer_search', 'Alpha')
            ->assertSee('Alpha 01')
            ->assertSee('Alpha 10')
            ->assertDontSee('Alpha 11');
    }

    public function test_a_customer_can_be_selected(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Inc']);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('customer_id', $customer->id)
            ->assertSet('customer_search', 'Acme Inc')
            ->assertSet('customerDropdownOpen', false);
    }

    public function test_the_add_customer_action_prefills_a_company_name(): void
    {
        Livewire::test('pages::invoices.create')
            ->set('customer_search', 'New Company')
            ->call('openCustomerModalForSearch')
            ->assertDispatched('open-customer-modal', companyName: 'New Company', email: '');
    }

    public function test_the_add_customer_action_prefills_an_email(): void
    {
        Livewire::test('pages::invoices.create')
            ->set('customer_search', 'new@example.com')
            ->call('openCustomerModalForSearch')
            ->assertDispatched('open-customer-modal', companyName: '', email: 'new@example.com');
    }

    public function test_a_newly_created_customer_is_selected(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Inc']);

        Livewire::test('pages::invoices.create')
            ->call('customerSaved', $customer->id, 'Acme Inc')
            ->assertSet('customer_id', $customer->id)
            ->assertSet('customer_search', 'Acme Inc');
    }

    public function test_an_invoice_can_be_created(): void
    {
        $customer = Customer::factory()->create();

        $component = Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->set('invoice_number', 'INV-TEST-1')
            ->set('issue_date', '2026-01-01')
            ->set('due_date', '2026-01-15')
            ->set('tax_rate', '10')
            ->set('tax_number', 'GST-123')
            ->set('description', 'Website redesign')
            ->set('items', [
                ['description' => 'Design services', 'quantity' => 2, 'rate' => 100],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::query()->firstOrFail();

        $component->assertRedirect(route('invoices.show', ['invoice' => $invoice]));

        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame('INV-TEST-1', $invoice->invoice_number);
        $this->assertSame('GST-123', $invoice->tax_number);
        $this->assertSame('Website redesign', $invoice->description);
        $this->assertNull(Setting::current()->tax_number);
        $this->assertSame('200.00', $invoice->subtotal);
        $this->assertSame('20.00', $invoice->tax_amount);
        $this->assertSame('220.00', $invoice->total);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Design services',
            'amount' => 200,
        ]);
    }

    public function test_a_customer_is_required_to_create_an_invoice(): void
    {
        Livewire::test('pages::invoices.create')
            ->set('invoice_number', 'INV-TEST-1')
            ->set('items', [
                ['description' => 'Design services', 'quantity' => 1, 'rate' => 100],
            ])
            ->call('save')
            ->assertHasErrors(['customer_id' => 'required']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_the_default_invoice_identifier_uses_the_customer_prefix_and_next_sequence(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corporation']);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'ACME000001');
    }

    public function test_short_company_names_do_not_add_a_prefix(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'AB']);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', '000001');
    }

    public function test_a_custom_previous_identifier_is_incremented(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'SPRINT-52-INVOICE',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'SPRINT-53-INVOICE');
    }

    public function test_a_custom_previous_identifier_preserves_number_padding(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-0009',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'INV-0010');
    }

    public function test_a_previous_conventional_identifier_uses_the_next_default(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corporation']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'ACME000001',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'ACME000002');
    }

    public function test_a_custom_previous_identifier_without_numbers_falls_back_to_the_default(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corporation']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'SPRINT-INVOICE',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'ACME000002');
    }

    public function test_a_custom_invoice_identifier_is_not_overwritten_when_selecting_a_customer(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corporation']);

        Livewire::test('pages::invoices.create')
            ->set('invoice_number', 'CUSTOM-1')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'CUSTOM-1');
    }

    public function test_the_tax_number_edit_mode_can_be_toggled(): void
    {
        Livewire::test('pages::invoices.create')
            ->assertSet('editingTaxNumber', false)
            ->call('toggleTaxNumberEdit')
            ->assertSet('editingTaxNumber', true)
            ->call('toggleTaxNumberEdit')
            ->assertSet('editingTaxNumber', false);
    }

    public function test_the_first_numeric_group_is_incremented(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'SPRINT-52-INVOICE-2025',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'SPRINT-53-INVOICE-2025');
    }

    public function test_year_like_groups_are_skipped_when_incrementing(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '2025-SPRINT-52',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', '2025-SPRINT-53');
    }

    public function test_alphabetic_suffixes_are_incremented(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'INV-B');
    }

    public function test_alphabetic_suffixes_roll_over(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-Z',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'INV-AA');
    }

    public function test_alphabetic_suffixes_are_incremented_when_only_year_numbers_exist(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Sprint Co']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '2025-INV-A',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', '2025-INV-B');
    }

    public function test_a_customer_can_use_a_custom_invoice_prefix(): void
    {
        $customer = Customer::factory()->create([
            'company_name' => 'Acme Corporation',
            'invoice_prefix' => 'SPR',
        ]);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'SPR000001');
    }

    public function test_the_customer_prefix_is_stored_and_survives_a_company_rename(): void
    {
        $customer = Customer::factory()->create([
            'company_name' => 'Acme Corporation',
            'invoice_prefix' => null,
        ]);

        Livewire::test('pages::invoices.create')->call('selectCustomer', $customer->id);

        $this->assertSame('ACME', $customer->refresh()->invoice_prefix);

        $customer->update(['company_name' => 'Globex Corporation']);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'ACME000001');
    }

    public function test_the_invoice_identifier_can_be_reset_to_the_suggestion(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corporation']);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->set('invoice_number', 'CUSTOM-1')
            ->assertSet('invoice_number_customized', true)
            ->call('resetInvoiceIdentifier')
            ->assertSet('invoice_number_customized', false)
            ->assertSet('invoice_number', 'ACME000001');
    }

    public function test_the_invoice_sequence_is_persistent_when_invoices_are_deleted(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corporation']);

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->set('items', [
                ['description' => 'Design services', 'quantity' => 1, 'rate' => 100],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('ACME000001', $invoice->invoice_number);
        $this->assertSame(1, $invoice->invoice_sequence);

        $invoice->delete();

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->assertSet('invoice_number', 'ACME000002')
            ->set('items', [
                ['description' => 'Design services', 'quantity' => 1, 'rate' => 100],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $secondInvoice = Invoice::query()->firstOrFail();

        $this->assertSame('ACME000002', $secondInvoice->invoice_number);
        $this->assertSame(2, $secondInvoice->invoice_sequence);
    }

    public function test_creating_a_paid_invoice_sets_a_paid_date(): void
    {
        $customer = Customer::factory()->create();

        Livewire::test('pages::invoices.create')
            ->call('selectCustomer', $customer->id)
            ->set('status', 'paid')
            ->set('items', [
                ['description' => 'Design services', 'quantity' => 1, 'rate' => 100],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Invoice::query()->firstOrFail()->paid_at);
    }

    public function test_an_invoice_can_be_edited(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'ACME000001',
            'invoice_sequence' => 1,
            'description' => 'Old description',
            'status' => 'draft',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'tax_rate' => 10,
            'tax_number' => 'GST-OLD',
            'notes' => 'Old notes',
        ]);

        $invoice->items()->create([
            'description' => 'Old item',
            'quantity' => 1,
            'unit_price' => 100,
            'amount' => 100,
        ]);

        Livewire::test('pages::invoices.create', ['invoice' => $invoice])
            ->assertSet('invoice_number', 'ACME000001')
            ->assertSet('description', 'Old description')
            ->set('description', 'New description')
            ->set('status', 'sent')
            ->set('tax_number', 'GST-NEW')
            ->set('items', [
                ['description' => 'New item', 'quantity' => 2, 'rate' => 150],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('New description', $invoice->description);
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('GST-NEW', $invoice->tax_number);
        $this->assertSame('300.00', $invoice->subtotal);
        $this->assertSame('30.00', $invoice->tax_amount);
        $this->assertSame('330.00', $invoice->total);
        $this->assertSame(1, $invoice->items()->count());

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New item',
            'amount' => 300,
        ]);
    }

    public function test_a_custom_invoice_identifier_is_preserved_when_editing(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Corp']);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'SPRINT-52-INVOICE',
            'invoice_sequence' => 1,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'description' => 'Work',
            'quantity' => 1,
            'unit_price' => 100,
            'amount' => 100,
        ]);

        Livewire::test('pages::invoices.create', ['invoice' => $invoice])
            ->assertSet('invoice_number_customized', true)
            ->set('items', [
                ['description' => 'Work', 'quantity' => 1, 'rate' => 100],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('SPRINT-52-INVOICE', $invoice->fresh()->invoice_number);
    }

    public function test_editing_a_paid_invoice_sets_and_clears_paid_at(): void
    {
        $customer = Customer::factory()->create();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'paid_at' => null,
        ]);

        $invoice->items()->create([
            'description' => 'Work',
            'quantity' => 1,
            'unit_price' => 100,
            'amount' => 100,
        ]);

        Livewire::test('pages::invoices.create', ['invoice' => $invoice])
            ->set('status', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);

        Livewire::test('pages::invoices.create', ['invoice' => $invoice->fresh()])
            ->set('status', 'sent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($invoice->fresh()->paid_at);
    }
}
