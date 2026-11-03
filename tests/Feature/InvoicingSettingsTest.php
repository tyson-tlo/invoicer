<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('settings.invoicing'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_invoicing_settings_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('settings.invoicing'));

        $response->assertOk();
        $response->assertSee('Default GST / tax rate');
        $response->assertSee('Tax Registration #');
        $response->assertSee('Invoice email');
    }

    public function test_invoicing_settings_can_be_saved(): void
    {
        Livewire::test('pages::settings.invoicing')
            ->set('tax_rate', '12.5')
            ->set('tax_number', 'GST-123')
            ->set('invoice_email_subject', 'Invoice {invoice_number}')
            ->set('invoice_email_message', 'Hi {customer_name}')
            ->call('save')
            ->assertHasNoErrors();

        $settings = Setting::current();

        $this->assertSame('12.50', $settings->tax_rate);
        $this->assertSame('GST-123', $settings->tax_number);
        $this->assertSame('Invoice {invoice_number}', $settings->invoice_email_subject);
        $this->assertSame('Hi {customer_name}', $settings->invoice_email_message);
    }
}
