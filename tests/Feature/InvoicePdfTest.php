<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_pdf_can_be_viewed_inline(): void
    {
        $invoice = $this->invoice();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_an_invoice_pdf_can_be_downloaded(): void
    {
        $invoice = $this->invoice();

        $response = $this->get(route('invoices.pdf.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    private function invoice(): Invoice
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        $invoice->items()->create([
            'description' => 'Design services',
            'quantity' => 2,
            'unit_price' => 100,
            'amount' => 200,
        ]);

        return $invoice;
    }
}
