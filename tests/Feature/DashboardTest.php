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
        $response->assertSee('Recent payments');
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

    public function test_recent_payments_can_be_filtered_by_period(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PAID-20',
            'status' => 'sent',
            'total' => 1000,
        ]);

        $invoice->payments()->create([
            'amount' => 1000,
            'paid_at' => now()->subDays(20)->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'REF-20',
        ]);

        $invoice->refreshPaymentState();

        Livewire::test('pages::dashboard')
            ->assertSee('PAID-20')
            ->call('setPaidPeriod', 7)
            ->assertSet('paidPeriod', 7)
            ->assertDontSee('PAID-20')
            ->call('setPaidPeriod', 90)
            ->assertSet('paidPeriod', 90)
            ->assertSee('PAID-20');
    }

    public function test_payment_totals_are_shown_for_each_period(): void
    {
        foreach ([
            [5, 100],
            [20, 200],
            [80, 400],
        ] as [$days, $amount]) {
            $invoice = Invoice::factory()->create([
                'status' => 'sent',
                'total' => $amount,
            ]);

            $invoice->payments()->create([
                'amount' => $amount,
                'paid_at' => now()->subDays($days)->toDateString(),
                'method' => 'bank_transfer',
            ]);

            $invoice->refreshPaymentState();
        }

        Livewire::test('pages::dashboard')
            ->assertSee('$100.00')
            ->assertSee('$300.00')
            ->assertSee('$700.00');
    }

    public function test_unverified_users_can_access_the_dashboard(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_partial_payments_are_shown_on_the_dashboard(): void
    {
        Invoice::factory()->create([
            'invoice_number' => 'PARTIAL-1',
            'status' => 'sent',
            'total' => 1000,
            'amount_paid' => 400,
        ]);

        Livewire::test('pages::dashboard')
            ->assertSee('PARTIAL-1')
            ->assertSee('40%');
    }
}
