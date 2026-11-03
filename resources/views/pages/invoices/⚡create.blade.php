<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Support\InvoiceIdentifier;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invoice')] class extends Component {
    public ?Invoice $invoice = null;

    public ?int $customer_id = null;
    public string $customer_search = '';
    public bool $customerDropdownOpen = false;

    public string $invoice_number = '';
    public bool $invoice_number_customized = false;
    public string $description = '';
    public string $status = 'draft';
    public string $issue_date = '';
    public string $due_date = '';
    public string $tax_rate = '0';
    public string $tax_number = '';
    public bool $editingTaxNumber = false;
    public string $notes = '';

    public array $items = [];

    public function mount(?Invoice $invoice = null): void
    {
        $this->invoice = $invoice;

        if ($invoice) {
            $this->loadInvoice($invoice);

            return;
        }

        $settings = Setting::current();

        $this->invoice_number = InvoiceIdentifier::defaultIdentifier(null, InvoiceIdentifier::nextSequence());
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(14)->toDateString();
        $this->tax_rate = (string) $settings->tax_rate;
        $this->tax_number = $settings->tax_number ?? '';
        $this->items = [$this->newItem()];
    }

    private function loadInvoice(Invoice $invoice): void
    {
        $invoice->load(['customer', 'items']);

        $this->customer_id = $invoice->customer_id;
        $this->customer_search = $invoice->customer->company_name;
        $this->invoice_number = $invoice->invoice_number;
        $this->description = $invoice->description ?? '';
        $this->status = $invoice->status;
        $this->issue_date = $invoice->issue_date->toDateString();
        $this->due_date = $invoice->due_date->toDateString();
        $this->tax_rate = (string) $invoice->tax_rate;
        $this->tax_number = $invoice->tax_number ?? '';
        $this->notes = $invoice->notes ?? '';

        $this->items = $invoice->items
            ->map(fn (InvoiceItem $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'rate' => $item->unit_price,
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items = [$this->newItem()];
        }

        $this->invoice_number_customized = $invoice->invoice_number !== InvoiceIdentifier::defaultIdentifier(
            $invoice->customer,
            $invoice->invoice_sequence ?? $invoice->id,
        );
    }

    public function updatedCustomerSearch(): void
    {
        $this->customer_id = null;
        $this->customerDropdownOpen = true;

        if (! $this->invoice_number_customized) {
            $this->refreshSuggestedInvoiceNumber();
        }
    }

    public function updatedInvoiceNumber(): void
    {
        $this->invoice_number_customized = true;
    }

    public function selectCustomer(int $customerId): void
    {
        $this->applyCustomer(Customer::query()->findOrFail($customerId));
    }

    public function toggleTaxNumberEdit(): void
    {
        $this->editingTaxNumber = ! $this->editingTaxNumber;
    }

    public function resetInvoiceIdentifier(): void
    {
        $this->invoice_number_customized = false;

        $this->refreshSuggestedInvoiceNumber();
    }

    public function openCustomerModalForSearch(): void
    {
        $search = trim($this->customer_search);
        $isEmail = filter_var($search, FILTER_VALIDATE_EMAIL) !== false;

        $this->customerDropdownOpen = false;

        $this->dispatch(
            'open-customer-modal',
            companyName: $isEmail ? '' : $search,
            email: $isEmail ? $search : '',
        );
    }

    #[On('customer-saved')]
    public function customerSaved(int $customerId, string $companyName): void
    {
        $this->applyCustomer(Customer::query()->findOrFail($customerId));
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customerResults(): Collection
    {
        $search = trim($this->customer_search);

        return Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('company_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('contact_person', 'like', $term);
                });
            })
            ->orderBy('company_name')
            ->limit(10)
            ->get();
    }

    public function addItem(): void
    {
        $this->items[] = $this->newItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->validate();

        $customer = Customer::query()->findOrFail($this->customer_id);

        if ($customer->invoice_prefix === null) {
            $customer->update([
                'invoice_prefix' => InvoiceIdentifier::companyPrefix($customer->company_name),
            ]);
        }

        if ($this->invoice) {
            $invoice = $this->invoice;

            DB::transaction(function () use ($customer, $invoice): void {
                $identifier = $this->invoice_number_customized
                    ? $this->invoice_number
                    : InvoiceIdentifier::defaultIdentifier($customer, $invoice->invoice_sequence ?? $invoice->id);

                $updates = [
                    'customer_id' => $customer->id,
                    'invoice_number' => $identifier,
                    'description' => $this->description ?: null,
                    'status' => $this->status,
                    'issue_date' => $this->issue_date,
                    'due_date' => $this->due_date,
                    'tax_rate' => $this->tax_rate,
                    'tax_number' => $this->tax_number ?: null,
                    'subtotal' => $this->subtotal,
                    'tax_amount' => $this->tax,
                    'total' => $this->total,
                    'notes' => $this->notes ?: null,
                ];

                if ($this->status === 'paid' && ! $invoice->paid_at) {
                    $updates['paid_at'] = now();
                } elseif ($this->status !== 'paid') {
                    $updates['paid_at'] = null;
                    $updates['payment_method'] = null;
                    $updates['payment_reference'] = null;
                    $updates['payment_note'] = null;
                }

                $invoice->update($updates);

                $invoice->items()->delete();

                $this->storeItems($invoice);

                if ($invoice->payments()->exists()) {
                    $invoice->refreshPaymentState();
                }
            });

            Flux::toast(variant: 'success', text: __('Invoice updated.'));

            $this->redirectRoute('invoices.show', ['invoice' => $invoice], navigate: true);

            return;
        }

        $invoice = DB::transaction(function () use ($customer): Invoice {
            $sequence = InvoiceIdentifier::reserveSequence();

            $identifier = $this->invoice_number_customized
                ? $this->invoice_number
                : InvoiceIdentifier::defaultIdentifier($customer, $sequence);

            $invoice = Invoice::query()->create([
                'customer_id' => $customer->id,
                'invoice_number' => $identifier,
                'invoice_sequence' => $sequence,
                'description' => $this->description ?: null,
                'status' => $this->status,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'tax_rate' => $this->tax_rate,
                'tax_number' => $this->tax_number ?: null,
                'subtotal' => $this->subtotal,
                'tax_amount' => $this->tax,
                'total' => $this->total,
                'notes' => $this->notes ?: null,
                'paid_at' => $this->status === 'paid' ? now() : null,
            ]);

            $this->storeItems($invoice);

            return $invoice;
        });

        Flux::toast(variant: 'success', text: __('Invoice created.'));

        $this->redirectRoute('invoices.show', ['invoice' => $invoice], navigate: true);
    }

    private function storeItems(Invoice $invoice): void
    {
        foreach ($this->items as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['rate'],
                'amount' => round((float) $item['quantity'] * (float) $item['rate'], 2),
            ]);
        }
    }

    #[Computed]
    public function subtotal(): float
    {
        return collect($this->items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['rate'],
        );
    }

    #[Computed]
    public function tax(): float
    {
        return $this->subtotal * ((float) $this->tax_rate / 100);
    }

    #[Computed]
    public function total(): float
    {
        return $this->subtotal + $this->tax;
    }

    protected function rules(): array
    {
        $invoiceNumberRules = ['required', 'string', 'max:255'];

        if ($this->invoice) {
            $invoiceNumberRules[] = Rule::unique('invoices', 'invoice_number')->ignore($this->invoice->id);
        } elseif ($this->invoice_number_customized) {
            $invoiceNumberRules[] = Rule::unique('invoices', 'invoice_number');
        }

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_number' => $invoiceNumberRules,
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,sent,paid'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function applyCustomer(Customer $customer): void
    {
        $this->customer_id = $customer->id;
        $this->customer_search = $customer->company_name;
        $this->customerDropdownOpen = false;

        if ($customer->invoice_prefix === null) {
            $customer->update([
                'invoice_prefix' => InvoiceIdentifier::companyPrefix($customer->company_name),
            ]);
        }

        if (! $this->invoice_number_customized) {
            $this->refreshSuggestedInvoiceNumber();
        }
    }

    private function refreshSuggestedInvoiceNumber(): void
    {
        $customer = $this->customer_id
            ? Customer::query()->find($this->customer_id)
            : null;

        if ($customer && $this->invoice) {
            $this->invoice_number = InvoiceIdentifier::defaultIdentifier($customer, $this->invoiceSequence());
        } elseif ($customer) {
            $this->invoice_number = InvoiceIdentifier::suggest($customer);
        } else {
            $this->invoice_number = InvoiceIdentifier::defaultIdentifier(null, $this->invoiceSequence());
        }
    }

    private function invoiceSequence(): int
    {
        return $this->invoice?->invoice_sequence ?? $this->invoice?->id ?? InvoiceIdentifier::nextSequence();
    }

    private function newItem(): array
    {
        return [
            'description' => '',
            'quantity' => 1,
            'rate' => 0,
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $invoice ? __('Edit invoice') : __('New invoice') }}</flux:heading>
                <flux:subheading>{{ $invoice ? __('Update the details for this invoice.') : __('Fill in the details below to create an invoice.') }}</flux:subheading>
            </div>

            <div class="flex items-center gap-2">
                <flux:button :href="$invoice ? route('invoices.show', $invoice) : route('invoices')" variant="ghost" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" icon="check" wire:click="save">
                    {{ $invoice ? __('Save changes') : __('Create invoice') }}
                </flux:button>
            </div>
        </div>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:card class="flex flex-col gap-6">
                <flux:heading>{{ __('Invoice details') }}</flux:heading>

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Client') }}</flux:label>

                        <div class="relative" wire:click.outside="$set('customerDropdownOpen', false)">
                            <flux:input
                                wire:model.live.debounce.750ms="customer_search"
                                x-on:focus="$wire.set('customerDropdownOpen', true)"
                                icon="magnifying-glass"
                                placeholder="{{ __('Search customers by company or email...') }}"
                            />

                            @if ($customerDropdownOpen)
                                <div class="absolute z-20 mt-1 max-h-80 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    @forelse ($this->customerResults as $customer)
                                        <button
                                            type="button"
                                            wire:key="customer-result-{{ $customer->id }}"
                                            wire:click="selectCustomer({{ $customer->id }})"
                                            class="flex w-full flex-col items-start gap-0.5 px-4 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                        >
                                            <span class="font-medium">{{ $customer->company_name }}</span>

                                            @if ($customer->email)
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $customer->email }}</span>
                                            @endif
                                        </button>
                                    @empty
                                        <div class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('No customers found.') }}
                                        </div>
                                    @endforelse

                                    <button
                                        type="button"
                                        wire:click="openCustomerModalForSearch"
                                        class="flex w-full items-center gap-2 border-t border-zinc-200 px-4 py-3 text-start text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700"
                                    >
                                        <span class="text-lg leading-none">+</span>
                                        {{ $customer_search !== '' ? __('Add customer ":name"', ['name' => $customer_search]) : __('Add customer') }}
                                    </button>
                                </div>
                            @endif
                        </div>

                        <flux:error name="customer_id" />
                    </flux:field>

                    <flux:input
                        wire:model="description"
                        label="{{ __('Description') }}"
                        placeholder="{{ __('Website redesign project') }}"
                        field:class="md:col-span-2"
                    />

                    <div>
                        <flux:field>
                            <x-form-label
                                :label="__('Invoice identifier')"
                                :tooltip="__('Generated from the client name and persistent invoice sequence. You can override it.')"
                            />

                            <flux:input wire:model.live="invoice_number" />

                            <flux:error name="invoice_number" />
                        </flux:field>

                        @if ($invoice_number_customized)
                            <flux:button
                                type="button"
                                variant="subtle"
                                size="xs"
                                class="mt-2"
                                wire:click="resetInvoiceIdentifier"
                            >
                                {{ __('Reset to suggested identifier') }}
                            </flux:button>
                        @endif
                    </div>

                    <flux:select wire:model="status" label="{{ __('Status') }}">
                        <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                        <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
                        @if ($invoice?->status === 'paid')
                            <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                        @endif
                    </flux:select>

                    <flux:input wire:model="issue_date" label="{{ __('Issue date') }}" type="date" />
                    <flux:input wire:model="due_date" label="{{ __('Due date') }}" type="date" />
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Line items') }}</flux:heading>

                    <flux:button type="button" variant="subtle" size="sm" icon="plus" wire:click="addItem">
                        {{ __('Add item') }}
                    </flux:button>
                </div>

                <div class="flex flex-col gap-4">
                    @foreach ($items as $index => $item)
                        <div class="grid gap-4 md:grid-cols-[1fr_100px_140px_140px_auto] md:items-end" wire:key="item-{{ $index }}">
                            <flux:input wire:model="items.{{ $index }}.description" label="{{ __('Description') }}" placeholder="{{ __('Design services') }}" />

                            <flux:input wire:model.live="items.{{ $index }}.quantity" label="{{ __('Qty') }}" type="number" min="0" step="0.01" />

                            <flux:input wire:model.live="items.{{ $index }}.rate" label="{{ __('Rate') }}" type="number" min="0" step="0.01" />

                            <flux:input label="{{ __('Amount') }}" value="${{ number_format((float) $item['quantity'] * (float) $item['rate'], 2) }}" readonly />

                            <flux:button
                                type="button"
                                variant="ghost"
                                icon="trash"
                                :disabled="count($items) === 1"
                                aria-label="{{ __('Remove item') }}"
                                wire:click="removeItem({{ $index }})"
                            />
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-end justify-between gap-6 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ __('Tax Registration #') }}</span>

                            @if (! $editingTaxNumber)
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $tax_number !== '' ? $tax_number : __('Not set') }}</span>

                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    icon="pencil-square"
                                    aria-label="{{ __('Edit tax registration number') }}"
                                    wire:click="toggleTaxNumberEdit"
                                />
                            @else
                                <flux:input wire:model="tax_number" size="sm" placeholder="{{ __('Tax Registration #') }}" class="w-48" />

                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    icon="check"
                                    aria-label="{{ __('Done editing') }}"
                                    wire:click="toggleTaxNumberEdit"
                                />
                            @endif
                        </div>
                    </div>

                    <div class="flex w-full max-w-xs flex-col gap-4">
                        <flux:input wire:model.live="tax_rate" label="{{ __('GST / Tax rate (%)') }}" type="number" min="0" max="100" step="0.01" class="max-w-40" />

                        <div class="flex flex-col gap-1 text-sm">
                            <div class="flex items-center justify-between">
                                <span>{{ __('Subtotal') }}</span>
                                <span>${{ number_format($this->subtotal, 2) }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span>{{ __('Tax') }}</span>
                                <span>${{ number_format($this->tax, 2) }}</span>
                            </div>

                            <div class="flex items-center justify-between border-t border-neutral-200 pt-1 font-medium dark:border-neutral-700">
                                <span>{{ __('Total') }}</span>
                                <span>${{ number_format($this->total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <flux:text class="text-xs">
                    {{ __('This only changes the tax registration number on this invoice.') }}
                    <flux:link :href="route('settings.invoicing')" target="_blank">
                        {{ __('Update the default in Settings') }}
                    </flux:link>
                </flux:text>
            </flux:card>

            <flux:card>
                <flux:textarea wire:model="notes" label="{{ __('Notes') }}" rows="4" placeholder="{{ __('Payment terms, thank you message, or anything else your client should know.') }}" />
            </flux:card>

            <div class="flex items-center justify-end gap-2">
                <flux:button :href="$invoice ? route('invoices.show', $invoice) : route('invoices')" variant="ghost" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $invoice ? __('Save changes') : __('Create invoice') }}
                </flux:button>
            </div>
        </form>

        <livewire:pages::customers.customer-modal />
    </div>
</section>
