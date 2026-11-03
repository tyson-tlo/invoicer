<?php

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Customers')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $perPage = '10';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    #[On('customer-saved')]
    public function refreshCustomers(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Customer::query()
            ->withCount('invoices')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('company_name', 'like', $term)
                        ->orWhere('contact_person', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderBy('company_name')
            ->paginate((int) $this->perPage);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
                <flux:subheading>{{ __('Manage the customers you invoice.') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-customer-modal')">
                {{ __('Create customer') }}
            </flux:button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:input
                wire:model.live.debounce.750ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search customers...') }}"
                class="max-w-md"
            />

            <flux:select wire:model.live="perPage" class="max-w-36">
                <flux:select.option value="10">{{ __('10 per page') }}</flux:select.option>
                <flux:select.option value="25">{{ __('25 per page') }}</flux:select.option>
                <flux:select.option value="50">{{ __('50 per page') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->customers as $customer)
                <div
                    class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-800"
                    wire:key="customer-{{ $customer->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading>{{ $customer->company_name }}</flux:heading>

                            @if ($customer->contact_person)
                                <flux:text class="mt-1">{{ $customer->contact_person }}</flux:text>
                            @endif
                        </div>

                        <flux:button
                            variant="ghost"
                            icon="pencil-square"
                            aria-label="{{ __('Edit customer') }}"
                            wire:click="$dispatch('open-customer-modal', { customerId: {{ $customer->id }} })"
                        />
                    </div>

                    <div class="flex flex-col gap-1 text-sm text-zinc-600 dark:text-zinc-400">
                        @if ($customer->email)
                            <span>{{ $customer->email }}</span>
                        @endif

                        @if ($customer->phone)
                            <span>{{ $customer->phone }}</span>
                        @endif

                        @php
                            $location = collect([
                                $customer->address_line1,
                                $customer->address_line2,
                                $customer->city,
                                $customer->state,
                                $customer->postal_code,
                                $customer->country,
                            ])->filter()->join(', ');
                        @endphp

                        @if ($location)
                            <span>{{ $location }}</span>
                        @endif

                        @if ($customer->invoice_prefix)
                            <span>{{ __('Invoice prefix: :prefix', ['prefix' => $customer->invoice_prefix]) }}</span>
                        @endif
                    </div>

                    <div class="mt-auto border-t border-neutral-200 pt-3 text-sm font-medium dark:border-neutral-700">
                        {{ trans_choice(':count invoice|:count invoices', $customer->invoices_count) }}
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-neutral-200 px-6 py-16 text-center dark:border-neutral-700">
                    <flux:heading>{{ __('No customers found') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Create a customer to start invoicing.') }}</flux:text>
                </div>
            @endforelse
        </div>

        <div>
            {{ $this->customers->links() }}
        </div>

        <livewire:pages::customers.customer-modal />
    </div>
</section>
