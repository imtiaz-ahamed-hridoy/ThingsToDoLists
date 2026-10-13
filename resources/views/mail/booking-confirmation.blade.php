<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Booking Confirmed</title>
<style>
  /* ── Reset ─────────────────────────────────────────────────────── */
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1a1a2e; }
  a { color: #4f46e5; text-decoration: none; }

  /* ── Wrapper ────────────────────────────────────────────────────── */
  .wrapper { max-width: 620px; margin: 32px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }

  /* ── Header ─────────────────────────────────────────────────────── */
  .header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 40px 32px; text-align: center; }
  .header .checkmark { width: 64px; height: 64px; background: rgba(255,255,255,0.15); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; }
  .header .checkmark svg { width: 32px; height: 32px; }
  .header h1 { color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
  .header p { color: rgba(255,255,255,0.85); font-size: 15px; margin-top: 6px; }

  /* ── Reference badge ────────────────────────────────────────────── */
  .ref-badge { margin: 24px 32px 0; background: #f0f0ff; border: 1.5px dashed #4f46e5; border-radius: 10px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
  .ref-badge .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; }
  .ref-badge .value { font-size: 18px; font-weight: 700; color: #4f46e5; letter-spacing: 1px; }

  /* ── Body ───────────────────────────────────────────────────────── */
  .body { padding: 28px 32px; }
  .greeting { font-size: 17px; font-weight: 600; margin-bottom: 12px; color: #1a1a2e; }
  .intro { font-size: 15px; color: #4b5563; line-height: 1.7; margin-bottom: 24px; }

  /* ── Details card ───────────────────────────────────────────────── */
  .card { background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; margin-bottom: 24px; }
  .card-title { background: #1a1a2e; color: #ffffff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 10px 20px; }
  .card-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #e5e7eb; }
  .card-row:last-child { border-bottom: none; }
  .card-row .row-label { font-size: 13px; color: #6b7280; font-weight: 500; }
  .card-row .row-value { font-size: 14px; color: #111827; font-weight: 600; text-align: right; max-width: 60%; }
  .row-value.price { color: #059669; font-size: 16px; }
  .row-value.status { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 20px; font-size: 12px; }

  /* ── e-Ticket button ────────────────────────────────────────────── */
  .ticket-section { text-align: center; margin-bottom: 24px; }
  .ticket-btn { display: inline-block; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff !important; font-size: 15px; font-weight: 700; padding: 14px 36px; border-radius: 50px; letter-spacing: 0.3px; }
  .ticket-btn:hover { opacity: 0.92; }

  /* ── Info note ──────────────────────────────────────────────────── */
  .info-note { background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; padding: 14px 18px; font-size: 13px; color: #92400e; line-height: 1.6; margin-bottom: 24px; }

  /* ── Footer ─────────────────────────────────────────────────────── */
  .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 24px 32px; text-align: center; }
  .footer p { font-size: 12px; color: #9ca3af; line-height: 1.7; }
  .footer a { color: #4f46e5; }
  .footer .brand { font-size: 14px; font-weight: 700; color: #4f46e5; margin-bottom: 6px; }
</style>
</head>
<body>
<div class="wrapper">

  {{-- ── Header ─────────────────────────────────────────────────── --}}
  <div class="header">
    <div class="checkmark">
      <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    </div>
    <h1>Booking Confirmed! 🎉</h1>
    <p>Your adventure is locked in. Get ready for an amazing experience.</p>
  </div>

  {{-- ── Reference badge ──────────────────────────────────────────── --}}
  <div class="ref-badge">
    <div>
      <div class="label">Booking Reference</div>
      <div class="value">{{ $booking->resolved_reference ?? $booking->reference }}</div>
    </div>
    <div style="text-align:right;">
      <div class="label">Status</div>
      <div style="margin-top:4px;">
        <span class="row-value status">{{ ucfirst($booking->status) }}</span>
      </div>
    </div>
  </div>

  {{-- ── Body ───────────────────────────────────────────────────────── --}}
  <div class="body">
    <p class="greeting">Hi {{ $booking->customer_name ?? 'Valued Customer' }},</p>
    <p class="intro">
      Great news! Your booking has been confirmed. Please keep this email as your receipt.
      All the details you need are listed below.
    </p>

    {{-- ── Booking details card ──────────────────────────────────────── --}}
    <div class="card">
      <div class="card-title">📋 Booking Details</div>

      @if($booking->product)
      <div class="card-row">
        <span class="row-label">Activity</span>
        <span class="row-value">{{ $booking->product->title }}</span>
      </div>
      @endif

      @if($booking->date)
      <div class="card-row">
        <span class="row-label">Date</span>
        <span class="row-value">{{ \Carbon\Carbon::parse($booking->date)->format('D, d M Y') }}</span>
      </div>
      @endif

      @if($booking->start_time)
      <div class="card-row">
        <span class="row-label">Start Time</span>
        <span class="row-value">{{ $booking->start_time }}</span>
      </div>
      @endif

      @if($booking->quantity)
      <div class="card-row">
        <span class="row-label">Guests</span>
        <span class="row-value">{{ $booking->quantity }}</span>
      </div>
      @endif

      <div class="card-row">
        <span class="row-label">Customer</span>
        <span class="row-value">{{ $booking->customer_name }}</span>
      </div>

      <div class="card-row">
        <span class="row-label">Email</span>
        <span class="row-value">{{ $booking->customer_email }}</span>
      </div>

      @if($booking->customer_phone)
      <div class="card-row">
        <span class="row-label">Phone</span>
        <span class="row-value">{{ $booking->customer_phone }}</span>
      </div>
      @endif

      <div class="card-row">
        <span class="row-label">Total Paid</span>
        <span class="row-value price">
          {{ strtoupper($booking->currency ?? $booking->booking_currency ?? 'EUR') }}
          {{ number_format((float)($booking->total_price ?? $booking->booking_amount ?? 0), 2) }}
        </span>
      </div>

      @if($booking->payment_gateway)
      <div class="card-row">
        <span class="row-label">Payment</span>
        <span class="row-value">{{ ucfirst($booking->payment_gateway) }}</span>
      </div>
      @endif

      @if($booking->confirmed_at)
      <div class="card-row">
        <span class="row-label">Confirmed At</span>
        <span class="row-value">{{ $booking->confirmed_at->format('d M Y, H:i') }} UTC</span>
      </div>
      @endif
    </div>

    {{-- ── e-Ticket button ─────────────────────────────────────────── --}}
    @if($booking->e_ticket_url)
    <div class="ticket-section">
      <a href="{{ $booking->e_ticket_url }}" class="ticket-btn">🎟 Download e-Ticket</a>
    </div>
    @endif

    {{-- ── Info note ────────────────────────────────────────────────── --}}
    <div class="info-note">
      ⚠️ <strong>Important:</strong> Please present this email or your booking reference
      <strong>{{ $booking->resolved_reference ?? $booking->reference }}</strong>
      at the venue. Arrive at least 15 minutes before your scheduled time.
    </div>

    <p style="font-size:14px; color:#4b5563; line-height:1.7;">
      Need help? Reply to this email or contact us at
      <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
      We're happy to help!
    </p>
  </div>

  {{-- ── Footer ──────────────────────────────────────────────────── --}}
  <div class="footer">
    <p class="brand">{{ config('app.name') }}</p>
    <p>
      This is an automated confirmation email. Please do not reply directly.<br>
      © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
      <a href="{{ config('app.frontend_url', config('app.url')) }}">Visit our website</a>
    </p>
  </div>

</div>
</body>
</html>
