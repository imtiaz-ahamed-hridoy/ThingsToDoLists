<?php

namespace App\Mail;

use App\Models\BookingInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * BookingConfirmationMail
 * =============================================================================
 * Sent to the customer when their booking is confirmed.
 *
 * DISPATCH VIA SendCustomerEmailJob ONLY (never send directly):
 *
 *   SendCustomerEmailJob::dispatch(
 *       new BookingConfirmationMail($booking),
 *       $booking->customer_email,
 *       'booking_confirmation_' . $booking->reference
 *   );
 *
 * IMPORTANT: This Mailable does NOT implement ShouldQueue. It is ALWAYS
 * dispatched via SendCustomerEmailJob which already runs on the 'email'
 * queue. Adding ShouldQueue here would cause Mail::to()->send() inside
 * that job to re-queue the email instead of sending it immediately.
 * =============================================================================
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BookingInfo $booking
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Confirmed — ' . ($this->booking->resolved_reference ?? $this->booking->reference),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.booking-confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
