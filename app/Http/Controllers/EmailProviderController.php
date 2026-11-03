<?php

namespace App\Http\Controllers;

use App\Models\EmailProviderSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Throwable;

class EmailProviderController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        if ($provider === 'gmail') {
            $settings = EmailProviderSetting::current();

            if (! filled($settings->google_client_id) || ! filled($settings->google_client_secret)) {
                return redirect()->route('settings.email')
                    ->with('error', __('Add Google OAuth credentials in Settings before connecting Gmail.'));
            }

            config([
                'services.google.client_id' => $settings->google_client_id,
                'services.google.client_secret' => $settings->google_client_secret,
                'services.google.redirect' => route('settings.email.callback', 'gmail'),
            ]);

            $driver = Socialite::driver('google');

            if (! $driver instanceof GoogleProvider) {
                throw new RuntimeException('The Google Socialite driver is not available.');
            }

            return $driver
                ->scopes([
                    'openid',
                    'profile',
                    'email',
                    'https://www.googleapis.com/auth/gmail.send',
                ])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->redirect();
        }

        if ($provider === 'outlook') {
            $settings = EmailProviderSetting::current();

            if (! filled($settings->microsoft_client_id) || ! filled($settings->microsoft_client_secret)) {
                return redirect()->route('settings.email')
                    ->with('error', __('Add Microsoft OAuth credentials in Settings before connecting Outlook.'));
            }

            $state = Str::random(40);
            session(['email_oauth_state' => $state]);

            $query = http_build_query([
                'client_id' => $settings->microsoft_client_id,
                'response_type' => 'code',
                'redirect_uri' => route('settings.email.callback', 'outlook'),
                'response_mode' => 'query',
                'scope' => 'offline_access openid profile email User.Read https://outlook.office.com/SMTP.Send',
                'state' => $state,
            ]);

            $tenant = (string) ($settings->microsoft_tenant ?: 'common');

            return redirect()->away("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$query}");
        }

        abort(404);
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        try {
            if ($provider === 'gmail') {
                $settings = EmailProviderSetting::current();

                config([
                    'services.google.client_id' => $settings->google_client_id,
                    'services.google.client_secret' => $settings->google_client_secret,
                    'services.google.redirect' => route('settings.email.callback', 'gmail'),
                ]);

                $user = Socialite::driver('google')->user();

                if (! $user instanceof SocialiteUser) {
                    throw new RuntimeException('The Google user response is invalid.');
                }

                $this->store(
                    provider: 'gmail',
                    email: $user->getEmail(),
                    accessToken: $user->token,
                    refreshToken: $user->refreshToken,
                    expiresIn: $user->expiresIn,
                );

                return redirect()->route('settings.email')->with('status', __('Gmail connected.'));
            }

            if ($provider === 'outlook') {
                $settings = EmailProviderSetting::current();

                $state = (string) $request->session()->pull('email_oauth_state');

                if ($state === '' || $request->string('state')->toString() !== $state) {
                    return redirect()->route('settings.email')
                        ->with('error', __('The Outlook connection request expired. Please try again.'));
                }

                $code = $request->string('code')->toString();

                if ($code === '') {
                    return redirect()->route('settings.email')
                        ->with('error', __('Outlook did not return an authorization code.'));
                }

                $tenant = (string) ($settings->microsoft_tenant ?: 'common');

                $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                    'client_id' => $settings->microsoft_client_id,
                    'client_secret' => $settings->microsoft_client_secret,
                    'code' => $code,
                    'redirect_uri' => route('settings.email.callback', 'outlook'),
                    'grant_type' => 'authorization_code',
                    'scope' => 'offline_access openid profile email User.Read https://outlook.office.com/SMTP.Send',
                ]);

                if ($response->failed()) {
                    report(new RuntimeException('Outlook token exchange failed: '.$response->body()));

                    return redirect()->route('settings.email')
                        ->with('error', __('Unable to connect Outlook.'));
                }

                $data = (array) $response->json();
                $accessToken = $data['access_token'] ?? null;

                if (! is_string($accessToken) || $accessToken === '') {
                    return redirect()->route('settings.email')
                        ->with('error', __('Outlook did not return an access token.'));
                }

                $email = $this->outlookEmail($accessToken, $data);

                if ($email === null) {
                    return redirect()->route('settings.email')
                        ->with('error', __('Unable to determine the Outlook account email.'));
                }

                $this->store(
                    provider: 'outlook',
                    email: $email,
                    accessToken: $accessToken,
                    refreshToken: is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
                    expiresIn: (int) ($data['expires_in'] ?? 3600),
                );

                return redirect()->route('settings.email')->with('status', __('Outlook connected.'));
            }
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('settings.email')
                ->with('error', __('Unable to connect the email provider.'));
        }

        abort(404);
    }

    private function store(
        string $provider,
        ?string $email,
        ?string $accessToken,
        ?string $refreshToken,
        ?int $expiresIn,
    ): void {
        $settings = EmailProviderSetting::current();

        $settings->update([
            'provider' => $provider,
            'account_email' => $email,
            'from_address' => $settings->from_address ?: $email,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => now()->addSeconds($expiresIn ?: 3600),
            'api_key' => null,
            'connected_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $tokenData
     */
    private function outlookEmail(string $accessToken, array $tokenData): ?string
    {
        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me?$select=mail,userPrincipalName');

        if ($response->successful()) {
            $email = $response->json('mail') ?: $response->json('userPrincipalName');

            if (is_string($email) && $email !== '') {
                return $email;
            }
        }

        $idToken = $tokenData['id_token'] ?? null;

        if (! is_string($idToken)) {
            return null;
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = $parts[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $claims = json_decode($decoded, true);

        if (! is_array($claims)) {
            return null;
        }

        $email = $claims['preferred_username'] ?? $claims['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
