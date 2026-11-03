<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
        $this->invoice->loadMissing(['customer', 'items']);
    }

    public function envelope(): Envelope
    {
        $settings = Setting::current();

        $subject = $settings->invoice_email_subject
            ?: __('Invoice :number from :app', [
                'number' => $this->invoice->invoice_number,
                'app' => config('app.name'),
            ]);

        return new Envelope(
            subject: $this->replacePlaceholders($subject),
        );
    }

    public function content(): Content
    {
        $settings = Setting::current();

        $message = $settings->invoice_email_message
            ?: __('Please find your invoice attached.');

        return new Content(
            markdown: 'mail.invoices.invoice',
            with: [
                'invoice' => $this->invoice,
                'messageBody' => $this->replacePlaceholders($message),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(InvoicePdf::class);

        return [
            Attachment::fromData(
                fn (): string => $pdf->output($this->invoice),
                $pdf->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }

    private function replacePlaceholders(string $text): string
    {
        return str_replace(
            ['{invoice_number}', '{customer_name}', '{due_date}', '{total}'],
            [
                $this->invoice->invoice_number,
                $this->invoice->customer->company_name,
                $this->invoice->due_date->format('M j, Y'),
                '$'.number_format((float) $this->invoice->total, 2),
            ],
            $text,
        );
    }
}
