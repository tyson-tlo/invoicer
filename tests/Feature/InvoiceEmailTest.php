<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\EmailProviderSetting;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_can_be_emailed_to_the_customer(): void
    {
        Mail::fake();

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'api_key' => 'SG.test',
            'from_address' => 'billing@example.com',
            'account_email' => 'billing@example.com',
        ]);

        $customer = Customer::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        $invoice->items()->create([
            'description' => 'Design services',
            'quantity' => 1,
            'unit_price' => 100,
            'amount' => 100,
        ]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('email')
            ->assertHasNoErrors();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->email);
        });

        $this->assertNotNull($invoice->fresh()->emailed_at);
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_an_invoice_is_not_emailed_without_a_customer_email(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => null]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('email');

        Mail::assertNothingSent();

        $this->assertNull($invoice->fresh()->emailed_at);
    }

    public function test_an_invoice_is_not_emailed_without_a_connected_provider(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        Livewire::test('pages::invoices.show', ['invoice' => $invoice])
            ->call('email');

        Mail::assertNothingSent();

        $this->assertNull($invoice->fresh()->emailed_at);
    }
}
