<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $amount
 * @property CarbonInterface $paid_at
 * @property string|null $method
 * @property string|null $reference
 * @property string|null $note
 * @property string|null $proof_path
 * @property string|null $proof_original_name
 * @property Invoice $invoice
 */
#[Fillable([
    'invoice_id',
    'amount',
    'paid_at',
    'method',
    'reference',
    'note',
    'proof_path',
    'proof_original_name',
])]
class Payment extends Model
{
    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'bank_transfer' => __('Bank transfer'),
            'credit_card' => __('Credit card'),
            'cash' => __('Cash'),
            'check' => __('Check'),
            'other' => __('Other'),
            default => $this->method ? ucfirst($this->method) : __('Unknown'),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Payment $payment): void {
            if ($payment->proof_path) {
                Storage::disk('local')->delete($payment->proof_path);
            }
        });
    }
}
