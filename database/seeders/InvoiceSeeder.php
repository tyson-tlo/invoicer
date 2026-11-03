<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Support\InvoiceIdentifier;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $demoEmails = [
            'billing@acme.test',
            'accounts@globex.test',
            'billing@initech.test',
            'finance@umbrella.test',
            'accounts@stark.test',
        ];

        $customers = Customer::query()
            ->whereIn('email', $demoEmails)
            ->get()
            ->keyBy('email');

        if ($customers->count() < count($demoEmails)) {
            $this->call(CustomerSeeder::class);

            $customers = Customer::query()
                ->whereIn('email', $demoEmails)
                ->get()
                ->keyBy('email');
        }

        if (Invoice::query()->whereIn('customer_id', $customers->pluck('id'))->exists()) {
            return;
        }

        $specs = [
            [
                'customer' => 'billing@acme.test',
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'description' => 'Website redesign',
                'items' => [
                    ['description' => 'Discovery and planning', 'quantity' => 10, 'rate' => 120],
                    ['description' => 'UX and UI design', 'quantity' => 20, 'rate' => 110],
                ],
                'notes' => 'Draft scope for the new marketing site.',
                'emailed_at' => null,
                'paid_at' => null,
            ],
            [
                'customer' => 'billing@initech.test',
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'description' => 'Monthly support retainer',
                'items' => [
                    ['description' => 'Support retainer', 'quantity' => 1, 'rate' => 1500],
                ],
                'notes' => 'Monthly support retainer for the current quarter.',
                'emailed_at' => null,
                'paid_at' => null,
            ],
            [
                'customer' => 'accounts@globex.test',
                'status' => 'sent',
                'issue_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->subDays(3)->toDateString(),
                'description' => 'Brand identity package',
                'items' => [
                    ['description' => 'Logo design', 'quantity' => 1, 'rate' => 2500],
                    ['description' => 'Brand guidelines', 'quantity' => 1, 'rate' => 800],
                ],
                'notes' => 'Please review the attached brand package.',
                'emailed_at' => now()->subDays(9),
                'paid_at' => null,
            ],
            [
                'customer' => 'finance@umbrella.test',
                'status' => 'sent',
                'issue_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'description' => 'Marketing website',
                'items' => [
                    ['description' => 'Website development', 'quantity' => 40, 'rate' => 125],
                ],
                'notes' => 'Website milestone invoice.',
                'emailed_at' => now()->subDays(4),
                'paid_at' => null,
            ],
            [
                'customer' => 'accounts@stark.test',
                'status' => 'sent',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'description' => 'Quarterly consulting',
                'items' => [
                    ['description' => 'Consulting hours', 'quantity' => 12, 'rate' => 200],
                ],
                'notes' => 'Quarterly consulting hours.',
                'emailed_at' => now(),
                'paid_at' => null,
            ],
            [
                'customer' => 'billing@acme.test',
                'status' => 'paid',
                'issue_date' => now()->subDays(25)->toDateString(),
                'due_date' => now()->subDays(10)->toDateString(),
                'description' => 'Landing page',
                'items' => [
                    ['description' => 'Landing page design', 'quantity' => 1, 'rate' => 1800],
                    ['description' => 'Copywriting', 'quantity' => 1, 'rate' => 400],
                ],
                'notes' => 'Paid in full. Thank you.',
                'emailed_at' => now()->subDays(24),
                'paid_at' => now()->subDays(20),
            ],
            [
                'customer' => 'accounts@globex.test',
                'status' => 'paid',
                'issue_date' => now()->subDays(8)->toDateString(),
                'due_date' => now()->addDays(6)->toDateString(),
                'description' => 'SEO audit',
                'items' => [
                    ['description' => 'SEO audit', 'quantity' => 1, 'rate' => 1200],
                ],
                'notes' => 'Paid in full. Thank you.',
                'emailed_at' => now()->subDays(7),
                'paid_at' => now()->subDays(3),
            ],
            [
                'customer' => 'accounts@stark.test',
                'status' => 'paid',
                'issue_date' => now()->subDays(70)->toDateString(),
                'due_date' => now()->subDays(55)->toDateString(),
                'description' => 'ERP integration',
                'items' => [
                    ['description' => 'Integration work', 'quantity' => 30, 'rate' => 175],
                ],
                'notes' => 'Paid in full. Thank you.',
                'emailed_at' => now()->subDays(69),
                'paid_at' => now()->subDays(60),
            ],
        ];

        $taxRate = 10.00;
        $taxNumber = Setting::current()->tax_number;

        foreach ($specs as $spec) {
            $customer = $customers->get($spec['customer']);

            if ($customer === null) {
                continue;
            }

            $subtotal = collect($spec['items'])->sum(
                fn (array $item): float => (float) $item['quantity'] * (float) $item['rate'],
            );
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $sequence = InvoiceIdentifier::reserveSequence();

            $invoice = Invoice::query()->create([
                'customer_id' => $customer->id,
                'invoice_number' => InvoiceIdentifier::defaultIdentifier($customer, $sequence),
                'invoice_sequence' => $sequence,
                'description' => $spec['description'],
                'status' => $spec['status'],
                'issue_date' => $spec['issue_date'],
                'due_date' => $spec['due_date'],
                'tax_rate' => $taxRate,
                'tax_number' => $taxNumber,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
                'notes' => $spec['notes'],
                'emailed_at' => $spec['emailed_at'],
                'paid_at' => $spec['paid_at'],
            ]);

            foreach ($spec['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['rate'],
                    'amount' => round((float) $item['quantity'] * (float) $item['rate'], 2),
                ]);
            }
        }
    }
}
