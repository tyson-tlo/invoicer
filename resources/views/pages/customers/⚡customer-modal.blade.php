<?php

use App\Models\Customer;
use App\Support\InvoiceIdentifier;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $showModal = false;
    public ?int $customerId = null;

    public string $company_name = '';
    public string $invoice_prefix = '';
    public string $contact_person = '';
    public string $email = '';
    public string $phone = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = '';
    public string $notes = '';

    #[On('open-customer-modal')]
    public function open(?int $customerId = null, string $companyName = '', string $email = ''): void
    {
        $this->resetValidation();
        $this->customerId = $customerId;

        if ($customerId) {
            $this->setCustomer(Customer::query()->findOrFail($customerId));
        } else {
            $this->resetCustomerFields();

            $this->company_name = $companyName;
            $this->email = $email;
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->invoice_prefix = trim($this->invoice_prefix);

        if ($this->invoice_prefix === '') {
            $this->invoice_prefix = InvoiceIdentifier::companyPrefix($this->company_name);
        }

        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($this->customerId) {
            $customer = Customer::query()->findOrFail($this->customerId);

            $customer->update($validated);

            $message = __('Customer updated.');
        } else {
            $customer = Customer::query()->create($validated);

            $message = __('Customer created.');
        }

        $this->showModal = false;

        $this->dispatch('customer-saved', customerId: $customer->id, companyName: $customer->company_name);

        Flux::toast(variant: 'success', text: $message);
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    private function setCustomer(Customer $customer): void
    {
        $this->company_name = $customer->company_name;
        $this->invoice_prefix = $customer->invoice_prefix ?? '';
        $this->contact_person = $customer->contact_person ?? '';
        $this->email = $customer->email ?? '';
        $this->phone = $customer->phone ?? '';
        $this->address_line1 = $customer->address_line1 ?? '';
        $this->address_line2 = $customer->address_line2 ?? '';
        $this->city = $customer->city ?? '';
        $this->state = $customer->state ?? '';
        $this->postal_code = $customer->postal_code ?? '';
        $this->country = $customer->country ?? '';
        $this->notes = $customer->notes ?? '';
    }

    private function resetCustomerFields(): void
    {
        $this->reset([
            'company_name',
            'invoice_prefix',
            'contact_person',
            'email',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'notes',
        ]);
    }
}; ?>

<flux:modal wire:model="showModal" class="max-w-2xl">
    <form wire:submit="save" class="flex flex-col gap-6">
        <div>
            <flux:heading size="lg">
                {{ $customerId ? __('Edit customer') : __('Create customer') }}
            </flux:heading>

            <flux:subheading>
                {{ __('Add the billing details for this customer.') }}
            </flux:subheading>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="company_name" label="{{ __('Company name') }}" required field:class="md:col-span-2" />

            <flux:field class="md:col-span-2">
                <x-form-label
                    :label="__('Invoice prefix')"
                    :tooltip="__('Used for invoice identifiers. Leave blank to use the first 4 letters of the company name.')"
                />

                <flux:input wire:model="invoice_prefix" placeholder="{{ __('ACME') }}" />

                <flux:error name="invoice_prefix" />
            </flux:field>

            <flux:input wire:model="contact_person" label="{{ __('Contact person') }}" field:class="md:col-span-2" />
            <flux:input wire:model="email" label="{{ __('Email address') }}" type="email" field:class="md:col-span-2" />

            <flux:input wire:model="phone" label="{{ __('Phone number') }}" />
            <flux:input wire:model="country" label="{{ __('Country') }}" />

            <flux:input wire:model="address_line1" label="{{ __('Address') }}" field:class="md:col-span-2" />
            <flux:input wire:model="address_line2" label="{{ __('Address line 2') }}" field:class="md:col-span-2" />

            <flux:input wire:model="city" label="{{ __('City / location') }}" />
            <flux:input wire:model="state" label="{{ __('State / region') }}" />

            <flux:input wire:model="postal_code" label="{{ __('Postal / ZIP code') }}" />

            <flux:textarea wire:model="notes" label="{{ __('Notes') }}" rows="3" field:class="md:col-span-2" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:button type="button" variant="filled" wire:click="close">
                {{ __('Cancel') }}
            </flux:button>

            <flux:button type="submit" variant="primary">
                {{ $customerId ? __('Save changes') : __('Create customer') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
