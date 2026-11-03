<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_be_created_from_the_modal(): void
    {
        Livewire::test('pages::customers.customer-modal')
            ->call('open')
            ->set('company_name', 'Acme Inc')
            ->set('email', 'billing@acme.test')
            ->set('contact_person', 'Jane Doe')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-saved')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('customers', [
            'company_name' => 'Acme Inc',
            'email' => 'billing@acme.test',
            'contact_person' => 'Jane Doe',
        ]);
    }

    public function test_the_company_name_is_required(): void
    {
        Livewire::test('pages::customers.customer-modal')
            ->call('open')
            ->set('company_name', '')
            ->call('save')
            ->assertHasErrors(['company_name' => 'required']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_customer_can_be_edited_from_the_modal(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Old Name']);

        Livewire::test('pages::customers.customer-modal')
            ->call('open', $customer->id)
            ->assertSet('company_name', 'Old Name')
            ->set('company_name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'company_name' => 'New Name',
        ]);
    }

    public function test_the_modal_can_be_prefilled_with_a_company_name(): void
    {
        Livewire::test('pages::customers.customer-modal')
            ->call('open', null, 'New Company')
            ->assertSet('company_name', 'New Company')
            ->assertSet('email', '');
    }

    public function test_the_modal_can_be_prefilled_with_an_email(): void
    {
        Livewire::test('pages::customers.customer-modal')
            ->call('open', null, '', 'hello@example.com')
            ->assertSet('company_name', '')
            ->assertSet('email', 'hello@example.com');
    }

    public function test_a_blank_invoice_prefix_is_derived_from_the_company_name(): void
    {
        Livewire::test('pages::customers.customer-modal')
            ->call('open')
            ->set('company_name', 'Acme Inc')
            ->set('invoice_prefix', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'company_name' => 'Acme Inc',
            'invoice_prefix' => 'ACME',
        ]);
    }

    public function test_a_custom_invoice_prefix_can_be_saved(): void
    {
        Livewire::test('pages::customers.customer-modal')
            ->call('open')
            ->set('company_name', 'Acme Inc')
            ->set('invoice_prefix', 'ACM')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'company_name' => 'Acme Inc',
            'invoice_prefix' => 'ACM',
        ]);
    }
}
