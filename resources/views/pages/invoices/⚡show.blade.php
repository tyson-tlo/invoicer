<?php

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\EmailProviderMailer;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invoice')] class extends Component {
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['customer', 'items']);
    }

    public function email(): void
    {
        $this->invoice->loadMissing('customer');

        $email = $this->invoice->customer->email;

        if (! $email) {
            Flux::toast(variant: 'danger', text: __('Add an email address to this customer before sending the invoice.'));

            return;
        }

        $mailer = app(EmailProviderMailer::class);

        if (! $mailer->isConnected()) {
            Flux::toast(variant: 'danger', text: __('Connect an email provider in Settings before sending invoices.'));

            return;
        }

        try {
            $mailer->send(new InvoiceMail($this->invoice), $email);
        } catch (\Throwable $e) {
            report($e);

            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $updates = ['emailed_at' => now()];

        if ($this->invoice->status === 'draft') {
            $updates['status'] = 'sent';
        }

        $this->invoice->update($updates);

        Flux::toast(variant: 'success', text: __('Invoice emailed to :email.', ['email' => $email]));
    }

    public function markAsSent(): void
    {
        $this->invoice->update(['status' => 'sent']);

        Flux::toast(variant: 'success', text: __('Invoice marked as sent.'));
    }

    public function markAsPaid(): void
    {
        $this->invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Flux::toast(variant: 'success', text: __('Invoice marked as paid.'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Invoice :number', ['number' => $invoice->invoice_number]) }}</flux:heading>
                <flux:subheading>{{ $invoice->customer->company_name }}</flux:subheading>

                @if ($invoice->description)
                    <flux:text class="mt-1">{{ $invoice->description }}</flux:text>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button :href="route('invoices')" variant="ghost" wire:navigate>
                    {{ __('Back') }}
                </flux:button>

                <flux:tooltip :content="__('View PDF')">
                    <flux:button
                        :href="route('invoices.pdf', $invoice)"
                        variant="filled"
                        icon="eye"
                        size="sm"
                        target="_blank"
                        rel="noopener"
                        aria-label="{{ __('View PDF') }}"
                    />
                </flux:tooltip>

                <flux:tooltip :content="__('Download PDF')">
                    <flux:button
                        :href="route('invoices.pdf.download', $invoice)"
                        variant="filled"
                        icon="arrow-down-tray"
                        size="sm"
                        aria-label="{{ __('Download PDF') }}"
                    />
                </flux:tooltip>

                <flux:button :href="route('invoices.edit', $invoice)" variant="filled" icon="pencil-square" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>

                @if ($invoice->status === 'draft')
                    <flux:button variant="filled" icon="paper-airplane" wire:click="markAsSent">
                        {{ __('Mark as sent') }}
                    </flux:button>
                @endif

                @if ($invoice->status !== 'paid')
                    <flux:button variant="primary" icon="check" wire:click="markAsPaid">
                        {{ __('Mark as paid') }}
                    </flux:button>
                @endif

                <flux:button
                    variant="filled"
                    icon="envelope"
                    wire:click="email"
                    wire:loading.attr="disabled"
                    wire:target="email"
                    :disabled="! $invoice->customer->email"
                >
                    {{ __('Email invoice') }}
                </flux:button>
            </div>
        </div>

        @if ($invoice->emailed_at)
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ __('This invoice was emailed :date.', ['date' => $invoice->emailed_at->format('M j, Y g:i A')]) }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <flux:card class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Line items') }}</flux:heading>
                            <flux:subheading>{{ __('Services and products included on this invoice.') }}</flux:subheading>
                        </div>

                        <flux:badge>{{ ucfirst($invoice->status) }}</flux:badge>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                                    <th class="px-4 py-3 text-right font-medium">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-right font-medium">{{ __('Rate') }}</th>
                                    <th class="px-4 py-3 text-right font-medium">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $item)
                                    <tr class="border-t border-neutral-200 dark:border-neutral-700" wire:key="item-{{ $item->id }}">
                                        <td class="px-4 py-3">{{ $item->description }}</td>
                                        <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-right">${{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="px-4 py-3 text-right">${{ number_format((float) $item->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-end justify-between gap-6">
                        <div class="text-sm">
                            <div class="text-zinc-600 dark:text-zinc-400">{{ __('Tax Registration #') }}</div>
                            <div>{{ $invoice->tax_number ?: __('Not set') }}</div>
                        </div>

                        <div class="flex flex-col items-end gap-1 text-sm">
                            <div class="flex w-full max-w-xs items-center justify-between">
                                <span>{{ __('Subtotal') }}</span>
                                <span>${{ number_format((float) $invoice->subtotal, 2) }}</span>
                            </div>

                            <div class="flex w-full max-w-xs items-center justify-between">
                                <span>{{ __('Tax') }} ({{ rtrim(rtrim($invoice->tax_rate, '0'), '.') }}%)</span>
                                <span>${{ number_format((float) $invoice->tax_amount, 2) }}</span>
                            </div>

                            <div class="mt-2 flex w-full max-w-xs items-center justify-between border-t border-neutral-200 pt-2 font-medium dark:border-neutral-700">
                                <span>{{ __('Total') }}</span>
                                <span>${{ number_format((float) $invoice->total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </flux:card>

                @if ($invoice->notes)
                    <flux:card>
                        <flux:heading>{{ __('Notes') }}</flux:heading>
                        <flux:text class="mt-2 whitespace-pre-line">{{ $invoice->notes }}</flux:text>
                    </flux:card>
                @endif
            </div>

            <div class="flex flex-col gap-6">
                <flux:card class="flex flex-col gap-2">
                    <flux:heading>{{ __('Bill to') }}</flux:heading>

                    <div class="text-sm">
                        <div class="font-medium">{{ $invoice->customer->company_name }}</div>

                        @if ($invoice->customer->contact_person)
                            <div>{{ $invoice->customer->contact_person }}</div>
                        @endif

                        @if ($invoice->customer->email)
                            <div>{{ $invoice->customer->email }}</div>
                        @endif

                        @if ($invoice->customer->phone)
                            <div>{{ $invoice->customer->phone }}</div>
                        @endif

                        @php
                            $location = collect([
                                $invoice->customer->address_line1,
                                $invoice->customer->address_line2,
                                $invoice->customer->city,
                                $invoice->customer->state,
                                $invoice->customer->postal_code,
                                $invoice->customer->country,
                            ])->filter()->join(', ');
                        @endphp

                        @if ($location)
                            <div class="mt-2 text-zinc-600 dark:text-zinc-400">{{ $location }}</div>
                        @endif
                    </div>
                </flux:card>

                <flux:card class="flex flex-col gap-2 text-sm">
                    <flux:heading>{{ __('Invoice details') }}</flux:heading>

                    <div class="flex items-center justify-between gap-4">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Issue date') }}</span>
                        <span>{{ $invoice->issue_date->format('M j, Y') }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Due date') }}</span>
                        <span>{{ $invoice->due_date->format('M j, Y') }}</span>
                    </div>

                    @if ($invoice->paid_at)
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Paid on') }}</span>
                            <span>{{ $invoice->paid_at->format('M j, Y') }}</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-4">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Tax rate') }}</span>
                        <span>{{ rtrim(rtrim($invoice->tax_rate, '0'), '.') }}%</span>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>
</section>
