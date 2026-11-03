<?php

namespace Tests\Feature;

use App\Mail\TestEmailMail;
use App\Mail\Transport\GmailApiTransport;
use App\Models\EmailProviderSetting;
use App\Models\User;
use App\Services\EmailProviderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('settings.email'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_email_settings_page(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('settings.email'));

        $response->assertOk();
        $response->assertSee('Gmail');
        $response->assertSee('Outlook');
        $response->assertSee('Twilio SendGrid');
        $response->assertDontSee('Use log mailer');
    }

    public function test_the_log_option_is_only_shown_in_local(): void
    {
        app()->detectEnvironment(fn () => 'local');
        $this->actingAsUser();

        $response = $this->get(route('settings.email'));

        $response->assertOk();
        $response->assertSee('Use log mailer');
    }

    public function test_the_log_provider_can_be_activated_in_local(): void
    {
        app()->detectEnvironment(fn () => 'local');
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->call('connectLog')
            ->assertHasNoErrors();

        $this->assertSame('log', EmailProviderSetting::current()->provider);
        $this->assertTrue(app(EmailProviderMailer::class)->isConnected());
    }

    public function test_sendgrid_can_be_connected_and_the_api_key_is_encrypted(): void
    {
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->set('from_name', 'Billing')
            ->set('from_address', 'billing@example.com')
            ->set('sendgrid_api_key', 'SG.secret')
            ->call('connectSendgrid')
            ->assertHasNoErrors();

        $settings = EmailProviderSetting::current();

        $this->assertSame('sendgrid', $settings->provider);
        $this->assertSame('Billing', $settings->from_name);
        $this->assertSame('billing@example.com', $settings->from_address);
        $this->assertSame('SG.secret', $settings->api_key);
        $this->assertNotSame('SG.secret', DB::table('email_provider_settings')->value('api_key'));
    }

    public function test_sender_details_can_be_saved(): void
    {
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->set('from_name', 'Billing')
            ->set('from_address', 'billing@example.com')
            ->call('saveSenderDetails')
            ->assertHasNoErrors();

        $settings = EmailProviderSetting::current();

        $this->assertSame('Billing', $settings->from_name);
        $this->assertSame('billing@example.com', $settings->from_address);
    }

    public function test_disconnecting_clears_the_provider(): void
    {
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'from_address' => 'billing@example.com',
            'api_key' => 'SG.secret',
        ]);

        Livewire::test('pages::settings.email')
            ->call('disconnect')
            ->assertHasNoErrors();

        $settings = EmailProviderSetting::current();

        $this->assertNull($settings->provider);
        $this->assertNull($settings->api_key);
    }

    public function test_a_test_email_can_be_sent(): void
    {
        Mail::fake();
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'from_address' => 'billing@example.com',
            'account_email' => 'billing@example.com',
            'api_key' => 'SG.secret',
        ]);

        Livewire::test('pages::settings.email')
            ->set('test_email', 'recipient@example.com')
            ->call('sendTestEmail')
            ->assertHasNoErrors();

        Mail::assertSent(TestEmailMail::class);
    }

    public function test_sendgrid_uses_the_sendgrid_smtp_host(): void
    {
        $settings = EmailProviderSetting::current();
        $settings->update([
            'provider' => 'sendgrid',
            'from_address' => 'billing@example.com',
            'api_key' => 'SG.secret',
        ]);

        $config = app(EmailProviderMailer::class)->mailerConfig($settings->refresh());

        $this->assertSame('smtp.sendgrid.net', $config['host']);
        $this->assertSame('apikey', $config['username']);
        $this->assertSame('SG.secret', $config['password']);
    }

    public function test_oauth_connect_requires_credentials(): void
    {
        $this->actingAsUser();

        $this->get(route('settings.email.connect', 'gmail'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHas('error');
    }

    public function test_gmail_can_be_connected(): void
    {
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'google_client_id' => 'google-id',
            'google_client_secret' => 'google-secret',
        ]);

        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'sender@gmail.com',
            'token' => 'access-token',
            'refreshToken' => 'refresh-token',
            'expiresIn' => 3600,
        ]));

        $this->get(route('settings.email.callback', 'gmail'))
            ->assertRedirect(route('settings.email'));

        $settings = EmailProviderSetting::current();

        $this->assertSame('gmail', $settings->provider);
        $this->assertSame('sender@gmail.com', $settings->account_email);
        $this->assertSame('access-token', $settings->access_token);
        $this->assertSame('refresh-token', $settings->refresh_token);
        $this->assertSame('sender@gmail.com', $settings->from_address);
    }

    public function test_outlook_can_be_connected(): void
    {
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'microsoft_client_id' => 'microsoft-id',
            'microsoft_client_secret' => 'microsoft-secret',
            'microsoft_tenant' => 'contoso.onmicrosoft.com',
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/*' => Http::response([
                'mail' => 'sender@outlook.com',
            ]),
        ]);

        $this->withSession(['email_oauth_state' => 'state'])
            ->get(route('settings.email.callback', [
                'provider' => 'outlook',
                'code' => 'auth-code',
                'state' => 'state',
            ]))
            ->assertRedirect(route('settings.email'));

        $settings = EmailProviderSetting::current();

        $this->assertSame('outlook', $settings->provider);
        $this->assertSame('sender@outlook.com', $settings->account_email);
        $this->assertSame('access-token', $settings->access_token);
        $this->assertSame('refresh-token', $settings->refresh_token);
    }

    public function test_google_oauth_credentials_can_be_saved(): void
    {
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->set('google_client_id', 'google-id')
            ->set('google_client_secret', 'google-secret')
            ->call('saveGoogleCredentials')
            ->assertHasNoErrors();

        $settings = EmailProviderSetting::current();

        $this->assertSame('google-id', $settings->google_client_id);
        $this->assertSame('google-secret', $settings->google_client_secret);
        $this->assertNotSame('google-secret', DB::table('email_provider_settings')->value('google_client_secret'));
    }

    public function test_microsoft_oauth_credentials_can_be_saved(): void
    {
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->set('microsoft_client_id', 'microsoft-id')
            ->set('microsoft_client_secret', 'microsoft-secret')
            ->set('microsoft_tenant', 'contoso.onmicrosoft.com')
            ->call('saveMicrosoftCredentials')
            ->assertHasNoErrors();

        $settings = EmailProviderSetting::current();

        $this->assertSame('microsoft-id', $settings->microsoft_client_id);
        $this->assertSame('microsoft-secret', $settings->microsoft_client_secret);
        $this->assertSame('contoso.onmicrosoft.com', $settings->microsoft_tenant);
        $this->assertNotSame('microsoft-secret', DB::table('email_provider_settings')->value('microsoft_client_secret'));
    }

    private function actingAsUser(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_only_the_connected_provider_is_shown_by_default(): void
    {
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'from_address' => 'billing@example.com',
            'account_email' => 'billing@example.com',
            'api_key' => 'SG.secret',
        ]);

        Livewire::test('pages::settings.email')
            ->assertSee('Twilio SendGrid')
            ->assertSee('Change provider')
            ->assertDontSee('Connect Gmail')
            ->assertDontSee('Connect Outlook')
            ->assertDontSee('Use log mailer');
    }

    public function test_change_provider_reveals_other_providers(): void
    {
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'from_address' => 'billing@example.com',
            'account_email' => 'billing@example.com',
            'api_key' => 'SG.secret',
            'google_client_id' => 'google-id',
            'google_client_secret' => 'google-secret',
            'microsoft_client_id' => 'microsoft-id',
            'microsoft_client_secret' => 'microsoft-secret',
        ]);

        Livewire::test('pages::settings.email')
            ->call('changeProvider')
            ->assertSet('changingProvider', true)
            ->assertSee('Connect Gmail')
            ->assertSee('Connect Outlook')
            ->assertSee('Cancel');
    }

    public function test_cancelling_provider_change_hides_other_providers(): void
    {
        $this->actingAsUser();

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'from_address' => 'billing@example.com',
            'account_email' => 'billing@example.com',
            'api_key' => 'SG.secret',
            'google_client_id' => 'google-id',
            'google_client_secret' => 'google-secret',
            'microsoft_client_id' => 'microsoft-id',
            'microsoft_client_secret' => 'microsoft-secret',
        ]);

        Livewire::test('pages::settings.email')
            ->call('changeProvider')
            ->call('cancelProviderChange')
            ->assertSet('changingProvider', false)
            ->assertDontSee('Connect Gmail')
            ->assertDontSee('Connect Outlook')
            ->assertSee('Change provider');
    }

    public function test_connecting_sendgrid_from_change_mode_collapses_the_options(): void
    {
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->call('changeProvider')
            ->set('from_name', 'Billing')
            ->set('from_address', 'billing@example.com')
            ->set('sendgrid_api_key', 'SG.secret')
            ->call('connectSendgrid')
            ->assertSet('changingProvider', false)
            ->assertDontSee('Connect Gmail')
            ->assertDontSee('Connect Outlook')
            ->assertSee('Change provider');
    }

    public function test_expired_gmail_tokens_are_refreshed_before_sending(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ]),
        ]);

        $settings = EmailProviderSetting::current();
        $settings->update([
            'provider' => 'gmail',
            'account_email' => 'sender@gmail.com',
            'google_client_id' => 'google-id',
            'google_client_secret' => 'google-secret',
            'access_token' => 'old-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subHour(),
        ]);

        $config = app(EmailProviderMailer::class)->mailerConfig($settings->refresh());

        $this->assertSame('new-access-token', $config['access_token']);
        $this->assertSame('new-access-token', $settings->fresh()->access_token);
    }

    public function test_gmail_uses_the_gmail_api_transport(): void
    {
        $settings = EmailProviderSetting::current();
        $settings->update([
            'provider' => 'gmail',
            'account_email' => 'sender@gmail.com',
            'google_client_id' => 'google-id',
            'google_client_secret' => 'google-secret',
            'access_token' => 'access-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $config = app(EmailProviderMailer::class)->mailerConfig($settings->refresh());

        $this->assertSame('gmail-api', $config['transport']);
        $this->assertSame('access-token', $config['access_token']);
    }

    public function test_gmail_api_transport_sends_the_raw_message(): void
    {
        Http::fake([
            'gmail.googleapis.com/*' => Http::response(['id' => 'message-id']),
        ]);

        $transport = new GmailApiTransport('access-token');
        $message = new RawMessage("From: sender@gmail.com\r\nTo: client@example.com\r\nSubject: Test\r\n\r\nHello");
        $envelope = new Envelope(new Address('sender@gmail.com'), [new Address('client@example.com')]);

        $transport->send($message, $envelope);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
                && is_string($request['raw'])
                && $request['raw'] !== '';
        });
    }

    public function test_the_gmail_redirect_uri_is_shown(): void
    {
        $this->actingAsUser();

        Livewire::test('pages::settings.email')
            ->assertSee('Google redirect URI')
            ->assertSee(route('settings.email.callback', 'gmail'));
    }
}
