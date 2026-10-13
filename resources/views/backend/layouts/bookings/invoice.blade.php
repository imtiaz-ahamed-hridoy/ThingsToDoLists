<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice — {{ $booking->booking_reference ?: $booking->reference }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* ── Reset & Base ── */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #1a1a2e;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        * { box-sizing: border-box; }

        /* ── Page setup ── */
        .invoice-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 40px;
        }

        /* ── Header ── */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1a1a2e;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .invoice-brand h1 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 2px;
            color: #1a1a2e;
            letter-spacing: -0.5px;
        }
        .invoice-brand .tagline {
            font-size: 11px;
            color: #6c757d;
        }
        .invoice-title-box {
            text-align: right;
        }
        .invoice-title-box h2 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            color: #1a1a2e;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .invoice-title-box .invoice-ref {
            font-size: 13px;
            color: #6c757d;
            margin-top: 2px;
        }

        /* ── Meta row (status badges) ── */
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .invoice-meta .status-group span {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-reserved { background: #6c757d; color: #fff; }
        .status-paid     { background: #0d6efd; color: #fff; }
        .status-confirmed{ background: #198754; color: #fff; }
        .status-cancelled{ background: #dc3545; color: #fff; }
        .status-failed   { background: #dc3545; color: #fff; }
        .status-completed{ background: #0dcaf0; color: #000; }
        .invoice-meta .date-meta {
            font-size: 11px;
            color: #6c757d;
        }

        /* ── Address sections ── */
        .invoice-addresses {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
        }
        .invoice-addresses > div {
            flex: 1;
        }
        .invoice-addresses h6 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6c757d;
            margin: 0 0 6px;
        }
        .invoice-addresses p {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
        }

        /* ── Summary section ── */
        .invoice-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        .invoice-summary .summary-item {
            flex: 1;
            padding: 10px 14px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .invoice-summary .summary-item label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            margin-bottom: 2px;
        }
        .invoice-summary .summary-item .value {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .invoice-summary .summary-item .value.total-amount {
            font-size: 20px;
            color: #198754;
        }

        /* ── Tables ── */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .invoice-table thead th {
            background: #1a1a2e;
            color: #fff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 8px 12px;
            text-align: left;
        }
        .invoice-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 12px;
        }
        .invoice-table tbody tr:nth-child(even) td {
            background: #f8f9fa;
        }
        .invoice-table .text-end { text-align: right; }
        .invoice-table .text-center { text-align: center; }

        /* ── Totals ── */
        .invoice-totals {
            margin-left: auto;
            width: 320px;
            margin-bottom: 30px;
        }
        .invoice-totals table {
            width: 100%;
        }
        .invoice-totals td {
            padding: 4px 12px;
            font-size: 12px;
        }
        .invoice-totals .total-row td {
            border-top: 2px solid #1a1a2e;
            font-weight: 800;
            font-size: 16px;
            padding-top: 8px;
            color: #1a1a2e;
        }

        /* ── Payment details ── */
        .invoice-payment {
            margin-bottom: 25px;
        }
        .invoice-payment h6 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6c757d;
            margin: 0 0 6px;
        }
        .invoice-payment table {
            width: 100%;
            max-width: 400px;
        }
        .invoice-payment td {
            padding: 3px 8px;
            font-size: 12px;
        }
        .invoice-payment td:last-child {
            text-align: right;
        }

        /* ── Footer ── */
        .invoice-footer {
            border-top: 2px solid #1a1a2e;
            padding-top: 15px;
            margin-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
        }
        .invoice-footer p { margin: 2px 0; }

        /* ── Cancellation / Refund info ── */
        .invoice-notes {
            margin-bottom: 20px;
        }
        .invoice-notes .note-box {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .note-cancelled { background: #fff3cd; border-left: 4px solid #ffc107; }
        .note-refunded  { background: #cff4fc; border-left: 4px solid #0dcaf0; }
        .note-failed    { background: #f8d7da; border-left: 4px solid #dc3545; }

        /* ── Print-only: hide action buttons ── */
        .no-print { display: block; }
        @media print {
            .no-print { display: none !important; }
            .invoice-wrapper { padding: 0; max-width: 100%; }
            .invoice-table thead th { background: #1a1a2e !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .invoice-meta { background: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-item { background: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .status-reserved, .status-paid, .status-confirmed, .status-cancelled, .status-failed, .status-completed { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { font-size: 11px; }
            .invoice-table tbody tr:nth-child(even) td { background: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 15mm 12mm; }
        }

        /* ── Toolbar ── */
        .invoice-toolbar {
            text-align: right;
            margin-bottom: 15px;
        }
        .invoice-toolbar .btn {
            display: inline-block;
            padding: 7px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin-left: 8px;
            cursor: pointer;
        }
        .btn-print {
            background: #1a1a2e;
            color: #fff;
            border: none;
        }
        .btn-print:hover { background: #2d2d5e; }
        .btn-back {
            background: #6c757d;
            color: #fff;
            border: none;
        }
        .btn-back:hover { background: #5a6268; }
    </style>
</head>
<body>

<div class="invoice-wrapper">

    {{-- TOOLBAR (hidden when printing) --}}
    <div class="invoice-toolbar no-print">
        <a href="{{ route('booking.show', $booking->booking_reference ?: $booking->reference) }}" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print / PDF
        </button>
    </div>

    {{-- HEADER --}}
    <div class="invoice-header">
        <div class="invoice-brand">
            <h1>{{ config('app.name', 'Royalloge') }}</h1>
            <div class="tagline">Booking Invoice</div>
        </div>
        <div class="invoice-title-box">
            <h2>INVOICE</h2>
            <div class="invoice-ref">#{{ $booking->booking_reference ?: $booking->reference }}</div>
        </div>
    </div>

    {{-- META (status badges + dates) --}}
    <div class="invoice-meta">
        <div class="status-group">
            @php
                $statusClass = match(strtoupper($booking->status ?? '')) {
                    'RESERVED' => 'status-reserved',
                    'PAID' => 'status-paid',
                    'CONFIRMED' => 'status-confirmed',
                    'CANCELLED' => 'status-cancelled',
                    'FAILED' => 'status-failed',
                    'COMPLETED' => 'status-completed',
                    default => 'status-reserved',
                };
            @endphp
            <span class="{{ $statusClass }}">{{ $booking->status ?? 'RESERVED' }}</span>
            @if($booking->provider_type)
                <span class="ms-2 text-muted" style="font-size:11px;">
                    <i class="fas fa-cloud"></i> {{ ucfirst($booking->provider_type) }}
                </span>
            @endif
        </div>
        <div class="date-meta">
            <div>Issued: {{ now()->format('M j, Y') }}</div>
            <div>Booking: {{ optional($booking->created_at)->format('M j, Y g:i A') }}</div>
        </div>
    </div>

    {{-- ADDRESSES --}}
    <div class="invoice-addresses">
        <div>
            <h6>Bill To / Customer</h6>
            <p>
                <strong>{{ $booking->customer_name ?? '—' }}</strong><br>
                @if($booking->customer_email)
                    {{ $booking->customer_email }}<br>
                @endif
                @if($booking->customer_phone)
                    {{ $booking->customer_phone }}
                @endif
            </p>
        </div>
        <div>
            <h6>Booking Info</h6>
            <p>
                <strong>Reference:</strong> {{ $booking->booking_reference ?: $booking->reference }}<br>
                <strong>Confirmation:</strong> {{ $booking->confirmation_code ?? '—' }}<br>
                @if($booking->product_confirmation_code)
                    <strong>Product CC:</strong> {{ $booking->product_confirmation_code }}<br>
                @endif
                @if($booking->booking_channel)
                    <strong>Channel:</strong> {{ $booking->booking_channel }}
                @endif
            </p>
        </div>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="invoice-summary">
        <div class="summary-item">
            <label>Product</label>
            <div class="value">{{ $booking->product?->title ?? $booking->title ?? '—' }}</div>
        </div>
        <div class="summary-item">
            <label>Quantity</label>
            <div class="value">{{ $booking->quantity ?? 1 }}</div>
        </div>
        <div class="summary-item">
            <label>Visit Date</label>
            <div class="value">{{ optional($booking->date)->format('M j, Y') ?: $booking->date ?? '—' }}</div>
        </div>
        <div class="summary-item">
            <label>Total Amount</label>
            <div class="value total-amount">
                {{ $booking->currency ?? 'EUR' }} {{ number_format((float) ($booking->total_price ?? $booking->booking_amount ?? $booking->amount ?? 0), 2) }}
            </div>
        </div>
    </div>

    {{-- BOOKING ITEMS TABLE --}}
    @if($booking->relationLoaded('details') && $booking->details->isNotEmpty())
        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:35%">Item</th>
                    <th style="width:15%">Type</th>
                    <th style="width:15%">Date</th>
                    <th style="width:8%" class="text-center">Qty</th>
                    <th style="width:12%" class="text-end">Price</th>
                    <th style="width:10%" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $subtotal = 0; @endphp
                @foreach($booking->details as $index => $detail)
                    @php
                        $lineTotal = ($detail->price ?? 0) * ($detail->quantity ?? 1);
                        $subtotal += $lineTotal;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $detail->title ?? 'Item ' . ($index + 1) }}</strong></td>
                        <td>{{ $detail->type ?? '—' }}</td>
                        <td>{{ optional($detail->date)->format('M j, Y') ?? $detail->date ?? '—' }}</td>
                        <td class="text-center">{{ $detail->quantity ?? 1 }}</td>
                        <td class="text-end">{{ ($detail->currency ?? 'EUR') . ' ' . number_format((float) ($detail->price ?? 0), 2) }}</td>
                        <td class="text-end">{{ ($detail->currency ?? 'EUR') . ' ' . number_format($lineTotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- TOTALS --}}
        <div class="invoice-totals">
            <table>
                <tr>
                    <td>Subtotal</td>
                    <td class="text-end">{{ $booking->currency ?? 'EUR' }} {{ number_format($subtotal, 2) }}</td>
                </tr>
                @if(($booking->total_price ?? 0) > 0 && abs($subtotal - (float)($booking->total_price ?? 0)) > 0.01)
                    <tr class="total-row">
                        <td><strong>Total Due</strong></td>
                        <td class="text-end"><strong>{{ $booking->currency ?? 'EUR' }} {{ number_format((float) ($booking->total_price ?? 0), 2) }}</strong></td>
                    </tr>
                @else
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td class="text-end"><strong>{{ $booking->currency ?? 'EUR' }} {{ number_format($subtotal, 2) }}</strong></td>
                    </tr>
                @endif
            </table>
        </div>
    @else
        {{-- Single line item summary --}}
        <table class="invoice-totals" style="width:100%; max-width:100%;">
            <tr>
                <td style="width:70%"><strong>Total Amount</strong></td>
                <td class="text-end" style="font-size:20px; font-weight:800;">
                    {{ $booking->currency ?? 'EUR' }} {{ number_format((float) ($booking->total_price ?? $booking->booking_amount ?? $booking->amount ?? 0), 2) }}
                </td>
            </tr>
        </table>
    @endif

    {{-- PAYMENT DETAILS --}}
    <div class="invoice-payment">
        <h6>Payment Details</h6>
        <table>
            <tr><td>Gateway</td><td>{{ $booking->payment_gateway ?? '—' }}</td></tr>
            <tr><td>Source</td><td>{{ $booking->payment_source ?? '—' }}</td></tr>
            <tr><td>Paid At</td><td>{{ optional($booking->paid_at)->format('M j, Y g:i A') ?? '—' }}</td></tr>
            <tr><td>Confirmed At</td><td>{{ optional($booking->confirmed_at)->format('M j, Y g:i A') ?? '—' }}</td></tr>
            @if($booking->stripe_session_id)
                <tr><td>Stripe Session</td><td><code>{{ $booking->stripe_session_id }}</code></td></tr>
            @endif
            @if($booking->paypal_order_id)
                <tr><td>PayPal Order</td><td><code>{{ $booking->paypal_order_id }}</code></td></tr>
            @endif
        </table>
    </div>

    {{-- CANCELLATION / REFUND / FAILURE NOTES --}}
    @if($booking->cancelled_at)
        <div class="invoice-notes">
            <div class="note-box note-cancelled">
                <strong><i class="fas fa-ban"></i> Cancelled</strong>
                {{ optional($booking->cancelled_at)->format('M j, Y g:i A') }} —
                {{ $booking->cancellation_reason ?? 'No reason provided.' }}
                @if($policy['can_refund'])
                    <br><em>This booking is eligible for refund.</em>
                @endif
            </div>
        </div>
    @endif

    @if($booking->refund_status)
        <div class="invoice-notes">
            <div class="note-box note-refunded">
                <strong><i class="fas fa-undo"></i> Refunded</strong>
                Status: {{ $booking->refund_status }} —
                Amount: {{ ($booking->refund_currency ?? 'EUR') . ' ' . number_format((float) ($booking->refund_amount ?? 0), 2) }} —
                {{ $booking->refund_reason ?? '' }}
            </div>
        </div>
    @endif

    @if($booking->failure_stage || $booking->failure_reason)
        <div class="invoice-notes">
            <div class="note-box note-failed">
                <strong><i class="fas fa-exclamation-circle"></i> Attention Required</strong><br>
                @if($booking->failure_stage) <strong>Stage:</strong> {{ $booking->failure_stage }}<br> @endif
                @if($booking->failure_reason) {{ $booking->failure_reason }} @endif
            </div>
        </div>
    @endif

    {{-- CANCELLATION POLICY --}}
    @if($policy['policy'])
        <div class="invoice-notes">
            <div class="note-box" style="background:#f8f9fa; border-left:4px solid #6c757d;">
                <strong><i class="fas fa-gavel"></i> Cancellation Policy</strong>
                <pre style="font-size:10px; margin:4px 0 0; white-space:pre-wrap;">{{ json_encode($policy['policy'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    @endif

    {{-- FOOTER --}}
    <div class="invoice-footer">
        <p><strong>{{ config('app.name', 'Royalloge') }}</strong> — Booking Invoice</p>
        <p>Reference: {{ $booking->booking_reference ?: $booking->reference }} | Generated: {{ now()->format('Y-m-d H:i') }}</p>
        <p>Thank you for your business.</p>
    </div>

</div>

</body>
</html>
