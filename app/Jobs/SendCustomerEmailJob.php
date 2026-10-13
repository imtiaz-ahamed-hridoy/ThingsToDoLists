<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailable;

/**
 * SendCustomerEmailJob
 * =============================================================================
 * A dedicated, high-priority job for ALL outbound customer emails.
 *
 * Usage:
 *   SendCustomerEmailJob::dispatch(
 *       new BookingConfirmationMail($booking),
 *       $booking->customer_email,
 *       'booking_confirmation_' . $booking->reference
 *   );
 * =============================================================================
 */
class SendCustomerEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ── Limits ────────────────────────────────────────────────────────────────
    // Allow up to 60 seconds for SMTP handshake and delivery before worker timeout.
    public int $timeout = 60;

    // Retry 3 times on transient network failures.
    public int $tries = 3;

    // Wait 15s before each retry.
    public int $backoff = 15;

    /**
     * @param  Mailable  $mailable    The fully constructed Mailable to send.
     * @param  string    $to          Recipient email address.
     * @param  string    $uniqueKey   Unique key to prevent duplicate sends.
     */
    public function __construct(
        public readonly Mailable $mailable,
        public readonly string $to,
        public readonly string $uniqueKey = ''
    ) {
        $this->onQueue('email');
    }

    /**
     * Handle the email send.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->to)->send($this->mailable);

            Log::debug('SendCustomerEmailJob: Email sent successfully.', [
                'to'         => $this->to,
                'mailable'   => class_basename($this->mailable),
                'unique_key' => $this->uniqueKey,
            ]);
        } catch (\Throwable $e) {
            Log::error('SendCustomerEmailJob: Failed to send email.', [
                'to'         => $this->to,
                'mailable'   => class_basename($this->mailable),
                'unique_key' => $this->uniqueKey,
                'error'      => $e->getMessage(),
                'attempt'    => $this->attempts(),
            ]);

            // Re-throw so the queue worker marks it as failed and retries.
            throw $e;
        }
    }

    /**
     * Handle a job that has exceeded all retry attempts.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical('SendCustomerEmailJob: All retries exhausted — email NOT delivered.', [
            'to'         => $this->to,
            'mailable'   => class_basename($this->mailable),
            'unique_key' => $this->uniqueKey,
            'error'      => $e->getMessage(),
        ]);
    }
}
