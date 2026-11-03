<?php

namespace App\Services;

use App\Models\EmailProviderSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailProviderMailer
{
    public function settings(): EmailProviderSetting
    {
        return EmailProviderSetting::current();
    }

    public function isConnected(): bool
    {
        $settings = $this->settings();

        return match ($settings->provider) {
            'log' => app()->environment('local'),
            'sendgrid' => filled($settings->api_key) && filled($settings->from_address),
            'gmail' => filled($settings->account_email)
                && filled($settings->google_client_id)
                && filled($settings->google_client_secret)
                && (filled($settings->access_token) || filled($settings->refresh_token)),
            'outlook' => filled($settings->account_email)
                && filled($settings->microsoft_client_id)
                && filled($settings->microsoft_client_secret)
                && (filled($settings->access_token) || filled($settings->refresh_token)),
            default => false,
        };
    }

    public function send(Mailable $mailable, string $to): void
    {
        $settings = $this->settings();

        if (! $this->isConnected()) {
            throw new RuntimeException(__('No email provider is connected.'));
        }

        $fromAddress = (string) ($settings->from_address ?: $settings->account_email ?: config('mail.from.address'));
        $fromName = (string) ($settings->from_name ?: config('mail.from.name'));

        if ($fromAddress === '') {
            throw new RuntimeException(__('Add a sender email address in the email settings.'));
        }

        $mailable->from($fromAddress, $fromName !== '' ? $fromName : null);

        if ($settings->provider === 'log') {
            Mail::mailer('log')->to($to)->send($mailable);

            return;
        }

        $mailerName = 'email-provider';

        config(['mail.mailers.'.$mailerName => $this->mailerConfig($settings)]);

        $manager = Mail::getFacadeRoot();

        if ($manager instanceof MailManager) {
            $manager->purge($mailerName);
        }

        Mail::mailer($mailerName)->to($to)->send($mailable);
    }

    /**
     * @return array<string, mixed>
     */
    public function mailerConfig(EmailProviderSetting $settings): array
    {
        $password = $settings->provider === 'sendgrid'
            ? (string) $settings->api_key
            : $this->accessToken($settings);

        $host = match ($settings->provider) {
            'gmail' => 'smtp.gmail.com',
            'outlook' => $this->outlookHost($settings->account_email),
            'sendgrid' => 'smtp.sendgrid.net',
            default => throw new RuntimeException(__('Unsupported email provider.')),
        };

        return [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => $host,
            'port' => 587,
            'username' => $settings->provider === 'sendgrid' ? 'apikey' : $settings->account_email,
            'password' => $password,
            'timeout' => null,
            'require_tls' => true,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ];
    }

    private function outlookHost(?string $email): string
    {
        $domain = strtolower((string) str($email)->after('@'));

        return in_array($domain, ['outlook.com', 'hotmail.com', 'live.com', 'msn.com'], true)
            ? 'smtp-mail.outlook.com'
            : 'smtp.office365.com';
    }

    private function accessToken(EmailProviderSetting $settings): string
    {
        if ($settings->access_token && (! $settings->token_expires_at || $settings->token_expires_at->isFuture())) {
            return $settings->access_token;
        }

        if (! $settings->refresh_token) {
            throw new RuntimeException(__('The email provider connection has expired. Reconnect it in Settings.'));
        }

        return $settings->provider === 'gmail'
            ? $this->refreshGoogleToken($settings)
            : $this->refreshMicrosoftToken($settings);
    }

    private function refreshGoogleToken(EmailProviderSetting $settings): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $settings->google_client_id,
            'client_secret' => $settings->google_client_secret,
            'refresh_token' => $settings->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(__('Unable to refresh the Gmail connection.'));
        }

        $data = (array) $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException(__('Unable to refresh the Gmail connection.'));
        }

        $settings->update([
            'access_token' => $accessToken,
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : $settings->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ]);

        return $accessToken;
    }

    private function refreshMicrosoftToken(EmailProviderSetting $settings): string
    {
        $tenant = (string) ($settings->microsoft_tenant ?: 'common');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id' => $settings->microsoft_client_id,
            'client_secret' => $settings->microsoft_client_secret,
            'refresh_token' => $settings->refresh_token,
            'grant_type' => 'refresh_token',
            'scope' => 'offline_access openid profile email User.Read https://outlook.office.com/SMTP.Send',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(__('Unable to refresh the Outlook connection.'));
        }

        $data = (array) $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException(__('Unable to refresh the Outlook connection.'));
        }

        $settings->update([
            'access_token' => $accessToken,
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : $settings->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ]);

        return $accessToken;
    }
}
