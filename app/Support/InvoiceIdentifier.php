<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class InvoiceIdentifier
{
    public static function companyPrefix(string $companyName): string
    {
        $letters = preg_replace('/[^A-Za-z0-9]/', '', $companyName) ?? '';

        if (strlen($letters) < 4) {
            return '';
        }

        return strtoupper(substr($letters, 0, 4));
    }

    public static function customerPrefix(Customer $customer): string
    {
        if ($customer->invoice_prefix !== null) {
            return $customer->invoice_prefix;
        }

        return self::companyPrefix($customer->company_name);
    }

    public static function defaultIdentifier(?Customer $customer, int $sequence): string
    {
        $prefix = $customer ? self::customerPrefix($customer) : '';

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public static function sequenceForInvoice(Invoice $invoice): int
    {
        return $invoice->invoice_sequence ?? $invoice->id;
    }

    public static function nextSequence(): int
    {
        $settings = Setting::current();

        $maxSequence = (int) Invoice::withTrashed()->max('invoice_sequence');
        $maxId = (int) Invoice::withTrashed()->max('id');

        return max((int) $settings->next_invoice_sequence, $maxSequence + 1, $maxId + 1);
    }

    public static function reserveSequence(): int
    {
        return DB::transaction(function (): int {
            $current = Setting::current();
            $settings = Setting::query()->lockForUpdate()->findOrFail($current->id);

            $sequence = self::nextSequence();

            $settings->update(['next_invoice_sequence' => $sequence + 1]);

            return $sequence;
        });
    }

    public static function suggest(Customer $customer): string
    {
        $lastInvoice = $customer->invoices()->latest('id')->first();

        if ($lastInvoice) {
            $conventional = self::defaultIdentifier($customer, self::sequenceForInvoice($lastInvoice));

            if ($lastInvoice->invoice_number !== $conventional) {
                $candidate = self::increment($lastInvoice->invoice_number);

                $attempts = 0;

                while ($candidate !== null
                    && Invoice::withTrashed()->where('invoice_number', $candidate)->exists()
                    && $attempts < 100) {
                    $candidate = self::increment($candidate);
                    $attempts++;
                }

                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return self::defaultIdentifier($customer, self::nextSequence());
    }

    public static function increment(string $identifier): ?string
    {
        preg_match_all('/\d+/', $identifier, $matches, PREG_OFFSET_CAPTURE);

        $groups = $matches[0];

        if ($groups !== []) {
            foreach ($groups as $group) {
                if (! self::isYearLike($group[0])) {
                    return self::incrementNumericGroup($identifier, $group[0], $group[1]);
                }
            }

            $alphabetic = self::incrementAlphabeticSuffix($identifier);

            if ($alphabetic !== null) {
                return $alphabetic;
            }

            $first = $groups[0];

            return self::incrementNumericGroup($identifier, $first[0], $first[1]);
        }

        return self::incrementAlphabeticSuffix($identifier);
    }

    private static function incrementNumericGroup(string $identifier, string $number, int $offset): string
    {
        $length = strlen($number);
        $incremented = str_pad((string) ((int) $number + 1), $length, '0', STR_PAD_LEFT);

        return substr_replace($identifier, $incremented, $offset, $length);
    }

    private static function incrementAlphabeticSuffix(string $identifier): ?string
    {
        preg_match_all('/[A-Za-z]+/', $identifier, $matches, PREG_OFFSET_CAPTURE);

        $groups = $matches[0];

        if ($groups === []) {
            return null;
        }

        $last = $groups[array_key_last($groups)];
        $token = $last[0];
        $offset = $last[1];
        $length = strlen($token);

        if ($length > 2 || $offset + $length !== strlen($identifier)) {
            return null;
        }

        if ($offset > 0 && ctype_alnum($identifier[$offset - 1])) {
            return null;
        }

        $isLower = strtolower($token) === $token;
        $incremented = self::incrementLetters(strtoupper($token));

        if ($isLower) {
            $incremented = strtolower($incremented);
        }

        return substr_replace($identifier, $incremented, $offset, $length);
    }

    private static function incrementLetters(string $letters): string
    {
        $characters = str_split($letters);

        for ($i = count($characters) - 1; $i >= 0; $i--) {
            if ($characters[$i] === 'Z') {
                $characters[$i] = 'A';

                continue;
            }

            $characters[$i] = chr(ord($characters[$i]) + 1);

            return implode('', $characters);
        }

        return 'A'.implode('', $characters);
    }

    private static function isYearLike(string $number): bool
    {
        if (strlen($number) !== 4 || str_starts_with($number, '0')) {
            return false;
        }

        $value = (int) $number;

        return $value >= 1900 && $value <= 2100;
    }
}
