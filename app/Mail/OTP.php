<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * NOTE: This Mailable is currently unused (dead code). If activated,
 * dispatch it via SendCustomerEmailJob which runs on the 'email' queue.
 * Do NOT implement ShouldQueue on this class directly — see notes on
 * BookingConfirmationMail for the double-queue pitfall.
 */
class OTP extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public User $user;
    public string $header_message;
    public ?SystemSetting  $systemSetting;
    public mixed $mailType;
    /**
     * Create a new message instance.
     */
    public function __construct(string $otp,User $user, string $message,$mailType = 'verify') {
        $this->otp = $otp;
        $this->user = $user;
        $this->header_message= $message;
        $this->systemSetting = SystemSetting::first();
        $this->mailType = $mailType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope {
        return new Envelope(
            subject: $this->header_message,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content {
        return new Content(
            view: $this->mailType === 'verify' ? 'mail.otp' : 'mail.forget-password',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array {
        return [];
    }
}
