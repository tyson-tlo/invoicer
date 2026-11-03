<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('customers'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_customers_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('customers'));

        $response->assertOk();
        $response->assertSee('Create customer');
        $response->assertSee('wire:model.live.debounce.750ms="search"', false);
    }

    public function test_customers_page_lists_customers_and_invoice_counts(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme Inc']);
        Invoice::factory()->count(2)->create(['customer_id' => $customer->id]);

        Livewire::test('pages::customers')
            ->assertSee('Acme Inc')
            ->assertSee('2 invoices');
    }

    public function test_customers_page_can_search(): void
    {
        Customer::factory()->create(['company_name' => 'Acme Inc']);
        Customer::factory()->create(['company_name' => 'Globex']);

        Livewire::test('pages::customers')
            ->set('search', 'Acme')
            ->assertSee('Acme Inc')
            ->assertDontSee('Globex');
    }

    public function test_customers_page_can_change_the_number_of_results_per_page(): void
    {
        Customer::factory()
            ->count(15)
            ->sequence(fn ($sequence) => ['company_name' => 'Customer '.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT)])
            ->create();

        Livewire::test('pages::customers')
            ->assertDontSee('Customer 15')
            ->set('perPage', '25')
            ->assertSee('Customer 15');
    }
}
