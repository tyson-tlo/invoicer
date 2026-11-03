<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class InvoicePdf
{
    public function output(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'items']);

        $settings = Setting::current();

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $settings,
            'logo' => $settings->logoDataUri(),
        ])->output();
    }

    public function filename(Invoice $invoice): string
    {
        return 'invoice-'.Str::slug($invoice->invoice_number).'.pdf';
    }
}
