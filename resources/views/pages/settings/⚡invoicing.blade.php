<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invoicing settings')] class extends Component {
    public string $tax_rate = '0';
    public string $tax_number = '';
    public string $invoice_email_subject = '';
    public string $invoice_email_message = '';

    public function mount(): void
    {
        $settings = Setting::current();

        $this->tax_rate = (string) $settings->tax_rate;
        $this->tax_number = $settings->tax_number ?? '';
        $this->invoice_email_subject = $settings->invoice_email_subject ?? '';
        $this->invoice_email_message = $settings->invoice_email_message ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'invoice_email_subject' => ['nullable', 'string', 'max:255'],
            'invoice_email_message' => ['nullable', 'string', 'max:5000'],
        ]);

        Setting::current()->update([
            'tax_rate' => $validated['tax_rate'],
            'tax_number' => $validated['tax_number'] ?: null,
            'invoice_email_subject' => $validated['invoice_email_subject'] ?: null,
            'invoice_email_message' => $validated['invoice_email_message'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Invoicing settings saved.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Invoicing settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Invoicing')" :subheading="__('Configure the defaults used when creating and sending invoices')">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Taxes') }}</flux:heading>
                    <flux:subheading>{{ __('These values prefill new invoices.') }}</flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="tax_rate" label="{{ __('Default GST / tax rate (%)') }}" type="number" min="0" max="100" step="0.01" />
                    <flux:input wire:model="tax_number" label="{{ __('Tax Registration #') }}" />
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Invoice email') }}</flux:heading>
                    <flux:subheading>{{ __('Customize the email sent when you email an invoice.') }}</flux:subheading>
                </div>

                <flux:input wire:model="invoice_email_subject" label="{{ __('Email subject') }}" placeholder="{{ __('Invoice {invoice_number} from {customer_name}') }}" />

                <flux:textarea
                    wire:model="invoice_email_message"
                    label="{{ __('Email message') }}"
                    rows="6"
                    placeholder="{{ __('Please find your invoice attached.') }}"
                    description="{{ __('Available placeholders: {invoice_number}, {customer_name}, {due_date}, {total}') }}"
                />
            </flux:card>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    {{ __('Save settings') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
