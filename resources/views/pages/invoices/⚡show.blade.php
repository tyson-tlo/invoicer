<?php

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\EmailProviderMailer;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Invoice')] class extends Component {
    use WithFileUploads;

    public Invoice $invoice;

    public bool $showPaymentModal = false;
    public string $payment_amount = '';
    public string $payment_date = '';
    public string $payment_method = '';
    public string $payment_reference = '';
    public string $payment_note = '';
    public $payment_proof = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['customer', 'items', 'payments']);
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

    public function openPaymentModal(): void
    {
        if ($this->invoice->balanceDue() <= 0) {
            Flux::toast(variant: 'danger', text: __('This invoice is already paid in full.'));

            return;
        }

        $this->resetValidation();
        $this->payment_amount = number_format($this->invoice->balanceDue(), 2, '.', '');
        $this->payment_date = now()->toDateString();
        $this->payment_method = '';
        $this->payment_reference = '';
        $this->payment_note = '';
        $this->payment_proof = null;
        $this->showPaymentModal = true;
    }

    public function markAsPaid(): void
    {
        if ($this->invoice->balanceDue() <= 0) {
            Flux::toast(variant: 'danger', text: __('This invoice is already paid in full.'));

            return;
        }

        $validated = $this->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$this->invoice->balanceDue()],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'in:bank_transfer,credit_card,cash,check,other'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $proofPath = null;
        $proofOriginalName = null;

        if ($this->payment_proof) {
            $storedPath = $this->payment_proof->store('payment-proofs', 'local');

            if ($storedPath !== false) {
                $proofPath = $storedPath;
                $proofOriginalName = $this->payment_proof->getClientOriginalName();
            }
        }

        $this->invoice->payments()->create([
            'amount' => $validated['payment_amount'],
            'paid_at' => $validated['payment_date'],
            'method' => $validated['payment_method'],
            'reference' => $validated['payment_reference'] ?: null,
            'note' => $validated['payment_note'] ?: null,
            'proof_path' => $proofPath,
            'proof_original_name' => $proofOriginalName,
        ]);

        $this->invoice->refreshPaymentState();
        $this->invoice->load('payments');

        $this->payment_proof = null;
        $this->showPaymentModal = false;

        Flux::toast(variant: 'success', text: __('Payment recorded.'));
    }

    public function removePayment(int $paymentId): void
    {
        $payment = $this->invoice->payments()->findOrFail($paymentId);

        $payment->delete();

        $this->invoice->refreshPaymentState();
        $this->invoice->load('payments');

        Flux::toast(variant: 'success', text: __('Payment removed.'));
    }

    public function deleteInvoice(): void
    {
        if ($this->invoice->hasRecordedPayment()) {
            Flux::toast(variant: 'danger', text: __('Delete all payments before deleting this invoice.'));

            return;
        }

        $this->invoice->delete();

        Flux::toast(variant: 'success', text: __('Invoice deleted.'));

        $this->redirectRoute('invoices', navigate: true);
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

                @if (! $invoice->isFullyPaid())
                    <flux:button variant="primary" icon="check" wire:click="openPaymentModal">
                        {{ __('Record payment') }}
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

                <flux:tooltip :content="$invoice->hasRecordedPayment() ? __('Delete all payments before deleting this invoice') : __('Delete invoice')">
                    <span>
                        <flux:button
                            variant="danger"
                            icon="trash"
                            size="sm"
                            :disabled="$invoice->hasRecordedPayment()"
                            wire:click="deleteInvoice"
                            wire:confirm="{{ __('Soft delete this invoice?') }}"
                            aria-label="{{ __('Delete invoice') }}"
                        />
                    </span>
                </flux:tooltip>
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

                        <flux:badge :color="$invoice->isFullyPaid() ? 'green' : ($invoice->isPartiallyPaid() ? 'yellow' : 'zinc')">
                            {{ ucfirst($invoice->displayStatus()) }}
                        </flux:badge>
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

                    <div class="flex items-center justify-between gap-4">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Tax rate') }}</span>
                        <span>{{ rtrim(rtrim($invoice->tax_rate, '0'), '.') }}%</span>
                    </div>
                </flux:card>

                @if ($invoice->payments->isNotEmpty())
                    <flux:card class="flex flex-col gap-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <flux:heading>{{ __('Payments') }}</flux:heading>
                                <flux:subheading>{{ __('Payments recorded against this invoice.') }}</flux:subheading>
                            </div>

                            <div class="text-right text-sm">
                                <div class="font-medium">
                                    ${{ number_format($invoice->amountPaid(), 2) }} / ${{ number_format((float) $invoice->total, 2) }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $invoice->paidPercentage() }}% {{ __('paid') }}
                                </div>
                            </div>
                        </div>

                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-green-500" style="width: {{ $invoice->paidPercentage() }}%"></div>
                        </div>

                        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($invoice->payments->sortByDesc('paid_at') as $payment)
                                <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0" wire:key="payment-{{ $payment->id }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium">${{ number_format((float) $payment->amount, 2) }}</span>
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $payment->paid_at->format('M j, Y') }}</span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ $payment->methodLabel() }}</span>

                                        @if ($payment->reference)
                                            <span>{{ __('Ref: :reference', ['reference' => $payment->reference]) }}</span>
                                        @endif

                                        @if ($payment->proof_path)
                                            <flux:link :href="route('payments.proof', $payment)" target="_blank">
                                                {{ __('View proof') }}
                                            </flux:link>
                                        @endif
                                    </div>

                                    @if ($payment->note)
                                        <flux:text class="text-sm whitespace-pre-line">{{ $payment->note }}</flux:text>
                                    @endif

                                    <div class="flex justify-end">
                                        <flux:button
                                            variant="ghost"
                                            icon="trash"
                                            size="sm"
                                            wire:click="removePayment({{ $payment->id }})"
                                            wire:confirm="{{ __('Remove this payment?') }}"
                                            aria-label="{{ __('Remove payment') }}"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endif
            </div>
        </div>
    </div>

        <flux:modal wire:model="showPaymentModal" class="max-w-lg">
            <form wire:submit="markAsPaid" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Record payment') }}</flux:heading>
                    <flux:subheading>{{ __('Record how this invoice was paid.') }}</flux:subheading>
                </div>

                <div class="flex flex-col gap-1 rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <span>{{ __('Invoice total') }}</span>
                        <span>${{ number_format((float) $invoice->total, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span>{{ __('Already paid') }}</span>
                        <span>${{ number_format($invoice->amountPaid(), 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between font-medium">
                        <span>{{ __('Balance due') }}</span>
                        <span>${{ number_format($invoice->balanceDue(), 2) }}</span>
                    </div>
                </div>

                <flux:input
                    wire:model="payment_amount"
                    label="{{ __('Amount') }}"
                    type="number"
                    min="0.01"
                    step="0.01"
                    :max="$invoice->balanceDue()"
                />

                <flux:input wire:model="payment_date" label="{{ __('Payment date') }}" type="date" />

                <flux:select wire:model="payment_method" label="{{ __('Payment method') }}">
                    <flux:select.option value="">{{ __('Select a method') }}</flux:select.option>
                    <flux:select.option value="bank_transfer">{{ __('Bank transfer') }}</flux:select.option>
                    <flux:select.option value="credit_card">{{ __('Credit card') }}</flux:select.option>
                    <flux:select.option value="cash">{{ __('Cash') }}</flux:select.option>
                    <flux:select.option value="check">{{ __('Check') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                </flux:select>

                <flux:input
                    wire:model="payment_reference"
                    label="{{ __('Reference number') }}"
                    placeholder="{{ __('Transaction, check, or bank reference') }}"
                />

                <flux:textarea
                    wire:model="payment_note"
                    label="{{ __('Note') }}"
                    rows="3"
                    placeholder="{{ __('Anything worth remembering about this payment.') }}"
                />

                <flux:input
                    wire:model="payment_proof"
                    label="{{ __('Payment proof') }}"
                    type="file"
                    accept="image/*,application/pdf"
                />

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="filled" wire:click="$set('showPaymentModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>

                    <flux:button type="submit" variant="primary">
                        {{ __('Record payment') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

</section>
