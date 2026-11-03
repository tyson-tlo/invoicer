<x-mail::message>
{!! nl2br(e($messageBody)) !!}

**{{ __('Invoice') }}:** {{ $invoice->invoice_number }}<br>
**{{ __('Amount due') }}:** ${{ number_format((float) $invoice->total, 2) }}<br>
**{{ __('Due date') }}:** {{ $invoice->due_date->format('M j, Y') }}

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
