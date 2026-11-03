<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>{{ __('Invoice') }} {{ $invoice->invoice_number }}</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 40px;
            color: #27272a;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        h1 {
            margin: 8px 0 0;
            font-size: 28px;
        }

        .muted { color: #71717a; }

        .header,
        .meta,
        .summary {
            width: 100%;
            border-collapse: collapse;
        }

        .header td,
        .meta td,
        .summary td {
            vertical-align: top;
        }

        .header td:last-child,
        .meta td:last-child {
            text-align: right;
        }

        .meta {
            margin-top: 32px;
        }

        .meta h2,
        .notes h2 {
            margin: 0 0 6px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #71717a;
        }

        .amount-due {
            margin-top: 24px;
            font-size: 24px;
            font-weight: bold;
        }

        .items {
            width: 100%;
            margin-top: 32px;
            border-collapse: collapse;
        }

        .items th {
            padding: 10px 12px;
            background: #f4f4f5;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #52525b;
        }

        .items td {
            padding: 12px;
            border-bottom: 1px solid #e4e4e7;
        }

        .items th:not(:first-child),
        .items td:not(:first-child) {
            text-align: right;
        }

        .totals {
            width: 280px;
            margin-top: 20px;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals td {
            padding: 6px 0;
        }

        .totals td:last-child {
            text-align: right;
        }

        .totals .total td {
            padding-top: 12px;
            border-top: 1px solid #d4d4d8;
            font-size: 16px;
            font-weight: bold;
        }

        .footer-summary {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .footer-summary td {
            vertical-align: bottom;
        }

        .footer-summary td:last-child {
            width: 280px;
        }

        .footer-summary .totals {
            margin-top: 0;
        }

        .notes {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e4e4e7;
        }

        .logo {
            max-height: 64px;
            max-width: 240px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" class="logo">
                @endif

                <h1>{{ __('Invoice') }}</h1>

                @if ($invoice->description)
                    <div class="muted" style="margin-top: 4px;">{{ $invoice->description }}</div>
                @endif
            </td>
            <td>
                <div><strong>{{ __('Invoice identifier') }}:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>{{ __('Issue date') }}:</strong> {{ $invoice->issue_date->format('M j, Y') }}</div>
                <div><strong>{{ __('Due date') }}:</strong> {{ $invoice->due_date->format('M j, Y') }}</div>
                <div><strong>{{ __('Status') }}:</strong> {{ ucfirst($invoice->status) }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <h2>{{ __('Bill to') }}</h2>

                <strong>{{ $invoice->customer->company_name }}</strong><br>

                @if ($invoice->customer->contact_person)
                    {{ $invoice->customer->contact_person }}<br>
                @endif

                @if ($invoice->customer->address_line1)
                    {{ $invoice->customer->address_line1 }}<br>
                @endif

                @if ($invoice->customer->address_line2)
                    {{ $invoice->customer->address_line2 }}<br>
                @endif

                @php
                    $location = collect([
                        $invoice->customer->city,
                        $invoice->customer->state,
                        $invoice->customer->postal_code,
                        $invoice->customer->country,
                    ])->filter()->join(', ');
                @endphp

                @if ($location)
                    {{ $location }}<br>
                @endif

                @if ($invoice->customer->email)
                    {{ $invoice->customer->email }}<br>
                @endif

                @if ($invoice->customer->phone)
                    {{ $invoice->customer->phone }}<br>
                @endif
            </td>
            <td>
                <h2>{{ __('Amount due') }}</h2>
                <div class="amount-due">${{ number_format((float) $invoice->total, 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th>{{ __('Qty') }}</th>
                <th>{{ __('Rate') }}</th>
                <th>{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>${{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-summary">
        <tr>
            <td>
                @if ($invoice->tax_number)
                    <div class="muted">{{ __('Tax Registration #') }}</div>
                    <div>{{ $invoice->tax_number }}</div>
                @endif
            </td>
            <td>
                <table class="totals">
                    <tr>
                        <td>{{ __('Subtotal') }}</td>
                        <td>${{ number_format((float) $invoice->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Tax') }} ({{ rtrim(rtrim($invoice->tax_rate, '0'), '.') }}%)</td>
                        <td>${{ number_format((float) $invoice->tax_amount, 2) }}</td>
                    </tr>
                    <tr class="total">
                        <td>{{ __('Total') }}</td>
                        <td>${{ number_format((float) $invoice->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <h2>{{ __('Notes') }}</h2>
            <div>{!! nl2br(e($invoice->notes)) !!}</div>
        </div>
    @endif
</body>
</html>
