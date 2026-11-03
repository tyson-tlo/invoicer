<?php

use App\Mail\TestEmailMail;
use App\Models\EmailProviderSetting;
use App\Services\EmailProviderMailer;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Email settings')] class extends Component {
    public string $from_name = '';
    public string $from_address = '';
    public string $sendgrid_api_key = '';
    public string $google_client_id = '';
    public string $google_client_secret = '';
    public string $microsoft_client_id = '';
    public string $microsoft_client_secret = '';
    public string $microsoft_tenant = 'common';
    public string $test_email = '';

    public function mount(): void
    {
        $settings = EmailProviderSetting::current();

        $this->from_name = $settings->from_name ?? '';
        $this->from_address = $settings->from_address ?? '';
        $this->google_client_id = $settings->google_client_id ?? '';
        $this->google_client_secret = $settings->google_client_secret ?? '';
        $this->microsoft_client_id = $settings->microsoft_client_id ?? '';
        $this->microsoft_client_secret = $settings->microsoft_client_secret ?? '';
        $this->microsoft_tenant = $settings->microsoft_tenant ?: 'common';
        $this->test_email = auth()->user()->email ?? '';
    }

    #[Computed]
    public function settings(): EmailProviderSetting
    {
        return EmailProviderSetting::current();
    }

    #[Computed]
    public function isConnected(): bool
    {
        return app(EmailProviderMailer::class)->isConnected();
    }

    public function saveSenderDetails(): void
    {
        $validated = $this->validate([
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
        ]);

        EmailProviderSetting::current()->update($validated);

        Flux::toast(variant: 'success', text: __('Sender details saved.'));
    }

    public function saveGoogleCredentials(): void
    {
        $validated = $this->validate([
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:500'],
        ]);

        EmailProviderSetting::current()->update([
            'google_client_id' => $validated['google_client_id'] ?: null,
            'google_client_secret' => $validated['google_client_secret'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Google OAuth credentials saved.'));
    }

    public function saveMicrosoftCredentials(): void
    {
        $validated = $this->validate([
            'microsoft_client_id' => ['nullable', 'string', 'max:255'],
            'microsoft_client_secret' => ['nullable', 'string', 'max:500'],
            'microsoft_tenant' => ['nullable', 'string', 'max:255'],
        ]);

        EmailProviderSetting::current()->update([
            'microsoft_client_id' => $validated['microsoft_client_id'] ?: null,
            'microsoft_client_secret' => $validated['microsoft_client_secret'] ?: null,
            'microsoft_tenant' => $validated['microsoft_tenant'] ?: 'common',
        ]);

        Flux::toast(variant: 'success', text: __('Microsoft OAuth credentials saved.'));
    }

    public function connectLog(): void
    {
        abort_unless(app()->environment('local'), 404);

        EmailProviderSetting::current()->update([
            'provider' => 'log',
            'account_email' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'api_key' => null,
            'connected_at' => now(),
        ]);

        Flux::toast(variant: 'success', text: __('The log mailer is now active.'));
    }

    public function connectSendgrid(): void
    {
        $validated = $this->validate([
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'sendgrid_api_key' => ['required', 'string', 'max:500'],
        ]);

        EmailProviderSetting::current()->update([
            'provider' => 'sendgrid',
            'from_name' => $validated['from_name'],
            'from_address' => $validated['from_address'],
            'account_email' => $validated['from_address'],
            'api_key' => $validated['sendgrid_api_key'],
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'connected_at' => now(),
        ]);

        $this->sendgrid_api_key = '';

        Flux::toast(variant: 'success', text: __('Twilio SendGrid connected.'));
    }

    public function disconnect(): void
    {
        EmailProviderSetting::current()->update([
            'provider' => null,
            'account_email' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'api_key' => null,
            'connected_at' => null,
        ]);

        Flux::toast(variant: 'success', text: __('Email provider disconnected.'));
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            app(EmailProviderMailer::class)->send(new TestEmailMail(), $this->test_email);
        } catch (\Throwable $e) {
            report($e);

            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Test email sent.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Email settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Email')" :subheading="__('Connect the email provider used to send invoices')">
        @php
            $gmailConfigured = filled($this->settings->google_client_id) && filled($this->settings->google_client_secret);
            $outlookConfigured = filled($this->settings->microsoft_client_id) && filled($this->settings->microsoft_client_secret);
        @endphp

        <div class="flex flex-col gap-6">
            @if (session('status'))
                <flux:callout variant="success">
                    <flux:callout.text>{{ session('status') }}</flux:callout.text>
                </flux:callout>
            @endif

            @if (session('error'))
                <flux:callout variant="danger">
                    <flux:callout.text>{{ session('error') }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:card class="flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Active provider') }}</flux:heading>
                        <flux:subheading>
                            @if ($this->isConnected)
                                {{ $this->settings->provider === 'log' ? __('Log mailer') : ucfirst((string) $this->settings->provider) }}

                                @if ($this->settings->account_email)
                                    ({{ $this->settings->account_email }})
                                @endif
                            @else
                                {{ __('Not connected') }}
                            @endif
                        </flux:subheading>
                    </div>

                    <flux:badge :color="$this->isConnected ? 'green' : 'zinc'">
                        {{ $this->isConnected ? __('Connected') : __('Disconnected') }}
                    </flux:badge>
                </div>

                @if ($this->settings->provider === 'log' && ! app()->environment('local'))
                    <flux:callout variant="warning">
                        <flux:callout.text>
                            {{ __('The log provider is only available in the local environment. Choose another provider.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @if ($this->isConnected)
                    <div>
                        <flux:button variant="danger" wire:click="disconnect">
                            {{ __('Disconnect') }}
                        </flux:button>
                    </div>
                @endif
            </flux:card>

            @if (app()->environment('local'))
                <flux:card class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading>{{ __('Log') }}</flux:heading>
                            <flux:subheading>{{ __('Write outgoing emails to the Laravel log. Available in local only.') }}</flux:subheading>
                        </div>

                        @if ($this->settings->provider === 'log')
                            <flux:badge color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </div>

                    @if ($this->settings->provider === 'log')
                        <div>
                            <flux:button variant="danger" wire:click="disconnect">
                                {{ __('Disconnect') }}
                            </flux:button>
                        </div>
                    @else
                        <div>
                            <flux:button variant="primary" wire:click="connectLog">
                                {{ __('Use log mailer') }}
                            </flux:button>
                        </div>
                    @endif
                </flux:card>
            @endif

            <flux:card class="flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Gmail') }}</flux:heading>
                        <flux:subheading>{{ __('Send invoices through a Google account.') }}</flux:subheading>
                    </div>

                    @if ($this->settings->provider === 'gmail')
                        <flux:badge color="green">{{ __('Connected') }}</flux:badge>
                    @endif
                </div>

                @if ($this->settings->provider === 'gmail' && $this->settings->account_email)
                    <flux:text>{{ __('Connected as :email', ['email' => $this->settings->account_email]) }}</flux:text>
                @endif

                <div class="flex flex-col gap-4">
                    <flux:input wire:model="google_client_id" label="{{ __('Google client ID') }}" />
                    <flux:input wire:model="google_client_secret" label="{{ __('Google client secret') }}" type="password" viewable />

                    <flux:text class="text-xs">
                        {{ __('Authorized redirect URI: :uri', ['uri' => route('settings.email.callback', 'gmail')]) }}
                    </flux:text>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button variant="filled" wire:click="saveGoogleCredentials">
                            {{ __('Save Google credentials') }}
                        </flux:button>

                        @if ($gmailConfigured)
                            @if ($this->settings->provider === 'gmail')
                                <flux:button variant="danger" wire:click="disconnect">
                                    {{ __('Disconnect Gmail') }}
                                </flux:button>
                            @else
                                <flux:button :href="route('settings.email.connect', 'gmail')" variant="primary">
                                    {{ __('Connect Gmail') }}
                                </flux:button>
                            @endif
                        @endif
                    </div>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Outlook') }}</flux:heading>
                        <flux:subheading>{{ __('Send invoices through a Microsoft account.') }}</flux:subheading>
                    </div>

                    @if ($this->settings->provider === 'outlook')
                        <flux:badge color="green">{{ __('Connected') }}</flux:badge>
                    @endif
                </div>

                @if ($this->settings->provider === 'outlook' && $this->settings->account_email)
                    <flux:text>{{ __('Connected as :email', ['email' => $this->settings->account_email]) }}</flux:text>
                @endif

                <div class="flex flex-col gap-4">
                    <flux:input wire:model="microsoft_client_id" label="{{ __('Microsoft client ID') }}" />
                    <flux:input wire:model="microsoft_client_secret" label="{{ __('Microsoft client secret') }}" type="password" viewable />
                    <flux:input wire:model="microsoft_tenant" label="{{ __('Microsoft tenant ID') }}" placeholder="common" />

                    <flux:text class="text-xs">
                        {{ __('Authorized redirect URI: :uri', ['uri' => route('settings.email.callback', 'outlook')]) }}
                    </flux:text>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button variant="filled" wire:click="saveMicrosoftCredentials">
                            {{ __('Save Microsoft credentials') }}
                        </flux:button>

                        @if ($outlookConfigured)
                            @if ($this->settings->provider === 'outlook')
                                <flux:button variant="danger" wire:click="disconnect">
                                    {{ __('Disconnect Outlook') }}
                                </flux:button>
                            @else
                                <flux:button :href="route('settings.email.connect', 'outlook')" variant="primary">
                                    {{ __('Connect Outlook') }}
                                </flux:button>
                            @endif
                        @endif
                    </div>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Twilio SendGrid') }}</flux:heading>
                        <flux:subheading>{{ __('Send invoices through Twilio SendGrid SMTP.') }}</flux:subheading>
                    </div>

                    @if ($this->settings->provider === 'sendgrid')
                        <flux:badge color="green">{{ __('Connected') }}</flux:badge>
                    @endif
                </div>

                <flux:input
                    wire:model="sendgrid_api_key"
                    label="{{ __('SendGrid API key') }}"
                    type="password"
                    viewable
                    placeholder="{{ $this->settings->provider === 'sendgrid' ? __('Enter a new key to replace the current one') : 'SG...' }}"
                />

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button variant="primary" wire:click="connectSendgrid">
                        {{ $this->settings->provider === 'sendgrid' ? __('Update SendGrid') : __('Connect SendGrid') }}
                    </flux:button>

                    @if ($this->settings->provider === 'sendgrid')
                        <flux:button variant="ghost" wire:click="disconnect">
                            {{ __('Disconnect') }}
                        </flux:button>
                    @endif
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Sender details') }}</flux:heading>
                    <flux:subheading>{{ __('Used as the from name and address on outgoing emails.') }}</flux:subheading>
                </div>

                <flux:input wire:model="from_name" label="{{ __('From name') }}" placeholder="{{ config('app.name') }}" />
                <flux:field>
                    <x-form-label
                        :label="__('From email')"
                        :tooltip="__('Gmail and Outlook usually require this to match the connected account.')"
                    />

                    <flux:input wire:model="from_address" type="email" placeholder="billing@example.com" />

                    <flux:error name="from_address" />
                </flux:field>

                <div>
                    <flux:button variant="primary" wire:click="saveSenderDetails">
                        {{ __('Save sender details') }}
                    </flux:button>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Send a test email') }}</flux:heading>
                    <flux:subheading>{{ __('Verify the connected provider before sending invoices.') }}</flux:subheading>
                </div>

                <flux:input wire:model="test_email" label="{{ __('Recipient') }}" type="email" />

                <div>
                    <flux:button variant="primary" wire:click="sendTestEmail" :disabled="! $this->isConnected">
                        {{ __('Send test email') }}
                    </flux:button>
                </div>
            </flux:card>
        </div>
    </x-pages::settings.layout>
</section>
