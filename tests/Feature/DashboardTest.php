<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Draft invoices');
        $response->assertSee('Sent but unpaid');
        $response->assertSee('Recently paid');
    }

    public function test_draft_invoices_are_listed(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'DRAFT-1',
            'status' => 'draft',
        ]);

        Livewire::test('pages::dashboard')
            ->assertSee('DRAFT-1')
            ->assertSee($invoice->customer->company_name);
    }

    public function test_sent_unpaid_invoices_are_listed(): void
    {
        Invoice::factory()->create([
            'invoice_number' => 'SENT-1',
            'status' => 'sent',
        ]);

        Livewire::test('pages::dashboard')->assertSee('SENT-1');
    }

    public function test_recently_paid_invoices_can_be_filtered_by_period(): void
    {
        Invoice::factory()->create([
            'invoice_number' => 'PAID-20',
            'status' => 'paid',
            'paid_at' => now()->subDays(20),
        ]);

        Livewire::test('pages::dashboard')
            ->assertSee('PAID-20')
            ->call('setPaidPeriod', 7)
            ->assertSet('paidPeriod', 7)
            ->assertDontSee('PAID-20')
            ->call('setPaidPeriod', 90)
            ->assertSet('paidPeriod', 90)
            ->assertSee('PAID-20');
    }

    public function test_paid_invoice_totals_are_shown_for_each_period(): void
    {
        Invoice::factory()->create([
            'status' => 'paid',
            'paid_at' => now()->subDays(5),
            'total' => 100,
        ]);

        Invoice::factory()->create([
            'status' => 'paid',
            'paid_at' => now()->subDays(20),
            'total' => 200,
        ]);

        Invoice::factory()->create([
            'status' => 'paid',
            'paid_at' => now()->subDays(80),
            'total' => 400,
        ]);

        Livewire::test('pages::dashboard')
            ->assertSee('$100.00')
            ->assertSee('$300.00')
            ->assertSee('$700.00');
    }
}
