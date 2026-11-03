<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $invoice_number
 * @property int|null $invoice_sequence
 * @property string|null $description
 * @property string $status
 * @property CarbonInterface $issue_date
 * @property CarbonInterface $due_date
 * @property string $tax_rate
 * @property string|null $tax_number
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total
 * @property string $amount_paid
 * @property string|null $notes
 * @property CarbonInterface|null $emailed_at
 * @property CarbonInterface|null $paid_at
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property string|null $payment_note
 * @property CarbonInterface|null $deleted_at
 * @property Customer $customer
 * @property Collection<int, InvoiceItem> $items
 */
#[Fillable([
    'customer_id',
    'invoice_number',
    'invoice_sequence',
    'description',
    'status',
    'issue_date',
    'due_date',
    'tax_rate',
    'tax_number',
    'subtotal',
    'tax_amount',
    'total',
    'amount_paid',
    'notes',
    'emailed_at',
    'paid_at',
    'payment_method',
    'payment_reference',
    'payment_note',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    public function hasRecordedPayment(): bool
    {
        return (float) $this->amount_paid > 0 || $this->paid_at !== null || $this->status === 'paid';
    }

    public function amountPaid(): float
    {
        return (float) $this->amount_paid;
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total - $this->amountPaid());
    }

    public function paidPercentage(): float
    {
        $total = (float) $this->total;

        return $total > 0 ? min(100, round($this->amountPaid() / $total * 100, 1)) : 0;
    }

    public function isFullyPaid(): bool
    {
        return $this->amountPaid() >= (float) $this->total;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->amountPaid() > 0 && ! $this->isFullyPaid();
    }

    public function displayStatus(): string
    {
        return $this->isPartiallyPaid() ? 'partial' : $this->status;
    }

    public function refreshPaymentState(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount');
        $latest = $this->payments()->latest('paid_at')->latest('id')->first();
        $fullyPaid = $totalPaid >= (float) $this->total;

        $this->forceFill([
            'amount_paid' => $totalPaid,
            'status' => $fullyPaid ? 'paid' : 'sent',
            'paid_at' => $fullyPaid ? $latest?->paid_at : null,
            'payment_method' => $latest?->method,
            'payment_reference' => $latest?->reference,
            'payment_note' => $latest?->note,
        ])->save();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'emailed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
