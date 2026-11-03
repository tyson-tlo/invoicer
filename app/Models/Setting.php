<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $tax_rate
 * @property string|null $tax_number
 * @property string|null $logo_path
 * @property string|null $icon_path
 * @property string|null $invoice_email_subject
 * @property string|null $invoice_email_message
 * @property int $next_invoice_sequence
 */
#[Fillable([
    'tax_rate',
    'tax_number',
    'logo_path',
    'icon_path',
    'invoice_email_subject',
    'invoice_email_message',
    'next_invoice_sequence',
])]
class Setting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tax_rate' => 0,
        'next_invoice_sequence' => 1,
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'tax_rate' => 0,
            'next_invoice_sequence' => 1,
        ]);
    }

    public function logoUrl(): ?string
    {
        return $this->publicDiskUrl($this->logo_path);
    }

    public function iconUrl(): ?string
    {
        return $this->publicDiskUrl($this->icon_path);
    }

    private function publicDiskUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path) ? $disk->url($path) : null;
    }

    public function logoDataUri(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($this->logo_path);

        return 'data:'.($mime ?: 'image/png').';base64,'.base64_encode($disk->get($this->logo_path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
        ];
    }
}
