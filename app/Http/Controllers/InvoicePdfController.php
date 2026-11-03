<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function view(Invoice $invoice, InvoicePdf $pdf): Response
    {
        return response($pdf->output($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($invoice).'"',
        ]);
    }

    public function download(Invoice $invoice, InvoicePdf $pdf): Response
    {
        return response($pdf->output($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
        ]);
    }
}
