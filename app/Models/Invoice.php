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
 * @property string|null $notes
 * @property CarbonInterface|null $emailed_at
 * @property CarbonInterface|null $paid_at
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
    'notes',
    'emailed_at',
    'paid_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

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
            'emailed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
