<?php

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Invoices')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $customer_id = '';
    public string $from = '';
    public string $to = '';
    public string $perPage = '10';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerId(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'customer_id', 'from', 'to']);

        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->status !== ''
            || $this->customer_id !== ''
            || $this->from !== ''
            || $this->to !== '';
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customers(): Collection
    {
        return Customer::query()
            ->orderBy('company_name')
            ->get(['id', 'company_name']);
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Invoice::query()
            ->with('customer')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('invoice_number', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('customer', function ($query) use ($term): void {
                            $query->where('company_name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->customer_id !== '', fn ($query) => $query->where('customer_id', (int) $this->customer_id))
            ->when($this->from !== '', fn ($query) => $query->whereDate('issue_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('issue_date', '<=', $this->to))
            ->latest('issue_date')
            ->latest('id')
            ->paginate((int) $this->perPage);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
                <flux:subheading>{{ __('Create and track invoices for your clients.') }}</flux:subheading>
            </div>

            <flux:button :href="route('invoices.create')" variant="primary" icon="plus" wire:navigate>
                {{ __('New invoice') }}
            </flux:button>
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-800">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-60 flex-1">
                    <flux:input
                        wire:model.live.debounce.750ms="search"
                        icon="magnifying-glass"
                        placeholder="{{ __('Search invoice, description, customer, or email...') }}"
                    />
                </div>

                <flux:select wire:model.live="status" class="max-w-40">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
                    <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="customer_id" class="max-w-56">
                    <flux:select.option value="">{{ __('All customers') }}</flux:select.option>
                    @foreach ($this->customers as $customer)
                        <flux:select.option value="{{ $customer->id }}">{{ $customer->company_name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="perPage" class="max-w-36">
                    <flux:select.option value="10">{{ __('10 per page') }}</flux:select.option>
                    <flux:select.option value="25">{{ __('25 per page') }}</flux:select.option>
                    <flux:select.option value="50">{{ __('50 per page') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <flux:input wire:model.live="from" label="{{ __('Issued from') }}" type="date" field:class="w-44" />
                <flux:input wire:model.live="to" label="{{ __('Issued to') }}" type="date" field:class="w-44" />

                @if ($this->hasFilters())
                    <flux:button type="button" variant="ghost" wire:click="clearFilters">
                        {{ __('Clear filters') }}
                    </flux:button>
                @endif

                <div class="ms-auto text-sm text-zinc-500 dark:text-zinc-400">
                    {{ trans_choice(':count invoice|:count invoices', $this->invoices->total()) }}
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Invoice') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Issued') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Due') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Total') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->invoices as $invoice)
                        <tr class="border-t border-neutral-200 dark:border-neutral-700" wire:key="invoice-{{ $invoice->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="font-medium hover:underline">
                                    {{ $invoice->invoice_number }}
                                </a>

                                @if ($invoice->description)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $invoice->customer->company_name }}</td>
                            <td class="px-4 py-3">{{ $invoice->issue_date->format('M j, Y') }}</td>
                            <td class="px-4 py-3">{{ $invoice->due_date->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format((float) $invoice->total, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200' => $invoice->status === 'draft',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' => $invoice->status === 'sent',
                                    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200' => $invoice->status === 'paid',
                                ])>
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:tooltip :content="__('Edit invoice')">
                                        <flux:button
                                            :href="route('invoices.edit', $invoice)"
                                            variant="ghost"
                                            icon="pencil-square"
                                            size="sm"
                                            wire:navigate
                                            aria-label="{{ __('Edit invoice') }}"
                                        />
                                    </flux:tooltip>

                                    <flux:tooltip :content="__('View PDF')">
                                        <flux:button
                                            :href="route('invoices.pdf', $invoice)"
                                            variant="ghost"
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
                                            variant="ghost"
                                            icon="arrow-down-tray"
                                            size="sm"
                                            aria-label="{{ __('Download PDF') }}"
                                        />
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <flux:heading>{{ $this->hasFilters() ? __('No matching invoices') : __('No invoices yet') }}</flux:heading>
                                <flux:text class="mt-1">{{ $this->hasFilters() ? __('Try adjusting your search or filters.') : __('Invoices you create will appear here.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $this->invoices->links() }}
        </div>
    </div>
</section>
