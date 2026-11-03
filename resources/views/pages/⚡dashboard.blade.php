<?php

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $paidPeriod = 30;

    public function setPaidPeriod(int $days): void
    {
        if (in_array($days, [7, 30, 90], true)) {
            $this->paidPeriod = $days;
        }
    }

    /**
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function draftInvoices(): Collection
    {
        return Invoice::query()
            ->with('customer')
            ->where('status', 'draft')
            ->latest('issue_date')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function sentInvoices(): Collection
    {
        return Invoice::query()
            ->with('customer')
            ->where('status', 'sent')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function recentPayments(): Collection
    {
        return Payment::query()
            ->with(['invoice.customer'])
            ->where('paid_at', '>=', now()->subDays($this->paidPeriod))
            ->latest('paid_at')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function largestOutstanding(): Collection
    {
        return Invoice::query()
            ->with('customer')
            ->where('status', 'sent')
            ->orderByRaw('(total - amount_paid) desc')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function draftCount(): int
    {
        return Invoice::query()->where('status', 'draft')->count();
    }

    #[Computed]
    public function draftTotal(): float
    {
        return (float) Invoice::query()->where('status', 'draft')->sum('total');
    }

    #[Computed]
    public function sentCount(): int
    {
        return Invoice::query()->where('status', 'sent')->count();
    }

    #[Computed]
    public function outstandingTotal(): float
    {
        return (float) Invoice::query()->where('status', 'sent')->sum('total')
            - (float) Invoice::query()->where('status', 'sent')->sum('amount_paid');
    }

    #[Computed]
    public function overdueCount(): int
    {
        return Invoice::query()
            ->where('status', 'sent')
            ->whereDate('due_date', '<', today())
            ->count();
    }

    #[Computed]
    public function overdueTotal(): float
    {
        return (float) Invoice::query()
            ->where('status', 'sent')
            ->whereDate('due_date', '<', today())
            ->sum('total')
            - (float) Invoice::query()
                ->where('status', 'sent')
                ->whereDate('due_date', '<', today())
                ->sum('amount_paid');
    }

    #[Computed]
    public function paidCount(): int
    {
        return Payment::query()
            ->where('paid_at', '>=', now()->subDays($this->paidPeriod))
            ->count();
    }

    #[Computed]
    public function paidTotal(): float
    {
        return (float) Payment::query()
            ->where('paid_at', '>=', now()->subDays($this->paidPeriod))
            ->sum('amount');
    }

    /**
     * @return array<int, float>
     */
    #[Computed]
    public function paidTotals(): array
    {
        $totals = [];

        foreach ([7, 30, 90] as $days) {
            $totals[$days] = (float) Payment::query()
                ->where('paid_at', '>=', now()->subDays($days))
                ->sum('amount');
        }

        return $totals;
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
                <flux:subheading>{{ __('Track drafts, unpaid invoices, and recent payments.') }}</flux:subheading>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button :href="route('customers')" variant="filled" icon="users" wire:navigate>
                    {{ __('Customers') }}
                </flux:button>

                <flux:button :href="route('invoices.create')" variant="primary" icon="plus" wire:navigate>
                    {{ __('New invoice') }}
                </flux:button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Outstanding') }}</flux:text>
                <div class="text-2xl font-semibold">${{ number_format($this->outstandingTotal, 2) }}</div>
                <flux:text class="text-xs">{{ trans_choice(':count sent invoice|:count sent invoices', $this->sentCount) }}</flux:text>
            </flux:card>

            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Overdue') }}</flux:text>
                <div @class(['text-2xl font-semibold', 'text-red-600 dark:text-red-400' => $this->overdueTotal > 0])>
                    ${{ number_format($this->overdueTotal, 2) }}
                </div>
                <flux:text class="text-xs">{{ trans_choice(':count overdue invoice|:count overdue invoices', $this->overdueCount) }}</flux:text>
            </flux:card>

            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Drafts') }}</flux:text>
                <div class="text-2xl font-semibold">${{ number_format($this->draftTotal, 2) }}</div>
                <flux:text class="text-xs">{{ trans_choice(':count draft invoice|:count draft invoices', $this->draftCount) }}</flux:text>
            </flux:card>

            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Collected') }}</flux:text>
                <div class="text-2xl font-semibold">${{ number_format($this->paidTotal, 2) }}</div>
                <flux:text class="text-xs">
                    {{ trans_choice(':count payment|:count payments', $this->paidCount) }}
                    · {{ __('last :days days', ['days' => $paidPeriod]) }}
                </flux:text>
            </flux:card>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="flex flex-col gap-6 xl:col-span-2">
                <flux:card class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Draft invoices') }}</flux:heading>
                            <flux:subheading>{{ __('Invoices that have not been sent yet.') }}</flux:subheading>
                        </div>

                        <flux:button :href="route('invoices')" variant="ghost" size="sm" wire:navigate>
                            {{ __('View all') }}
                        </flux:button>
                    </div>

                    @if ($this->draftInvoices->isEmpty())
                        <flux:text class="py-6 text-center">{{ __('No draft invoices.') }}</flux:text>
                    @else
                        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($this->draftInvoices as $invoice)
                                <a
                                    href="{{ route('invoices.show', $invoice) }}"
                                    wire:navigate
                                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 hover:opacity-80"
                                >
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ $invoice->invoice_number }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invoice->customer->company_name }}</div>
                                    </div>

                                    <div class="text-right text-sm">
                                        <div class="font-medium">${{ number_format((float) $invoice->total, 2) }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Issued :date', ['date' => $invoice->issue_date->format('M j, Y')]) }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>

                <flux:card class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Sent but unpaid') }}</flux:heading>
                            <flux:subheading>{{ __('Invoices waiting for payment.') }}</flux:subheading>
                        </div>

                        <flux:button :href="route('invoices')" variant="ghost" size="sm" wire:navigate>
                            {{ __('View all') }}
                        </flux:button>
                    </div>

                    @if ($this->sentInvoices->isEmpty())
                        <flux:text class="py-6 text-center">{{ __('No sent invoices waiting for payment.') }}</flux:text>
                    @else
                        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($this->sentInvoices as $invoice)
                                @php($isOverdue = $invoice->due_date->lt(today()))

                                <a
                                    href="{{ route('invoices.show', $invoice) }}"
                                    wire:navigate
                                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 hover:opacity-80"
                                >
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ $invoice->invoice_number }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invoice->customer->company_name }}</div>
                                    </div>

                                    <div class="text-right text-sm">
                                        <div class="font-medium">${{ number_format((float) $invoice->total, 2) }}</div>
                                        <div @class(['text-xs', 'text-red-600 dark:text-red-400' => $isOverdue, 'text-zinc-500 dark:text-zinc-400' => ! $isOverdue])>
                                            {{ $isOverdue ? __('Overdue :date', ['date' => $invoice->due_date->format('M j, Y')]) : __('Due :date', ['date' => $invoice->due_date->format('M j, Y')]) }}
                                        </div>

                                        @if ($invoice->amountPaid() > 0)
                                            <div class="text-xs text-amber-600 dark:text-amber-400">
                                                {{ $invoice->paidPercentage() }}% {{ __('paid') }} · ${{ number_format($invoice->balanceDue(), 2) }} {{ __('due') }}
                                            </div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>

                <flux:card class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ __('Recent payments') }}</flux:heading>
                            <flux:subheading>{{ __('Payments received in the selected period.') }}</flux:subheading>
                        </div>

                        <div class="flex items-center gap-1">
                            @foreach ([7, 30, 90] as $days)
                                <flux:button
                                    size="sm"
                                    :variant="$paidPeriod === $days ? 'primary' : 'ghost'"
                                    wire:click="setPaidPeriod({{ $days }})"
                                >
                                    {{ __(':days days', ['days' => $days]) }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                        <span>{{ __('Collected in the last :days days', ['days' => $paidPeriod]) }}</span>
                        <span class="font-semibold">
                            ${{ number_format($this->paidTotal, 2) }}
                            · {{ trans_choice(':count payment|:count payments', $this->paidCount) }}
                        </span>
                    </div>

                    @if ($this->recentPayments->isEmpty())
                        <flux:text class="py-6 text-center">{{ __('No payments in this period.') }}</flux:text>
                    @else
                        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($this->recentPayments as $payment)
                                <a
                                    href="{{ route('invoices.show', $payment->invoice) }}"
                                    wire:navigate
                                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 hover:opacity-80"
                                >
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ $payment->invoice->invoice_number }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $payment->invoice->customer->company_name }}</div>
                                    </div>

                                    <div class="text-right text-sm">
                                        <div class="font-medium">${{ number_format((float) $payment->amount, 2) }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Paid :date', ['date' => $payment->paid_at->format('M j, Y')]) }}
                                        </div>

                                        @if ($payment->invoice->isPartiallyPaid())
                                            <div class="text-xs text-amber-600 dark:text-amber-400">
                                                {{ $payment->invoice->paidPercentage() }}% {{ __('of invoice paid') }}
                                            </div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="flex flex-col gap-6">
                <flux:card class="flex flex-col gap-4">
                    <div>
                        <flux:heading>{{ __('Collected totals') }}</flux:heading>
                        <flux:subheading>{{ __('Collected revenue by period.') }}</flux:subheading>
                    </div>

                    @php($maxPaidTotal = max($this->paidTotals))

                    <div class="flex flex-col gap-3">
                        @foreach ([7, 30, 90] as $days)
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ __('Last :days days', ['days' => $days]) }}</span>
                                    <span class="font-medium">${{ number_format($this->paidTotals[$days], 2) }}</span>
                                </div>

                                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                                    <div
                                        class="h-full rounded-full bg-green-500"
                                        style="width: {{ $maxPaidTotal > 0 ? ($this->paidTotals[$days] / $maxPaidTotal) * 100 : 0 }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                <flux:card class="flex flex-col gap-4">
                    <div>
                        <flux:heading>{{ __('Largest outstanding') }}</flux:heading>
                        <flux:subheading>{{ __('Sent invoices with the highest balances.') }}</flux:subheading>
                    </div>

                    @if ($this->largestOutstanding->isEmpty())
                        <flux:text>{{ __('No outstanding invoices.') }}</flux:text>
                    @else
                        <div class="flex flex-col gap-3">
                            @foreach ($this->largestOutstanding as $invoice)
                                <a
                                    href="{{ route('invoices.show', $invoice) }}"
                                    wire:navigate
                                    class="flex items-center justify-between gap-3 text-sm hover:opacity-80"
                                >
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ $invoice->invoice_number }}</div>
                                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->customer->company_name }}</div>
                                    </div>

                                    <span class="font-medium">${{ number_format($invoice->balanceDue(), 2) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            </div>
        </div>
    </div>
</section>
