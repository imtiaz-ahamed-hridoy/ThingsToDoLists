<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * IMPORTANT: This Mailable does NOT implement ShouldQueue. It is ALWAYS
 * dispatched via SendCustomerEmailJob which already runs on the 'email'
 * queue. Adding ShouldQueue here would cause Mail::to()->send() inside
 * that job to re-queue the email instead of sending it immediately.
 */
class CustomUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectText;
    public $htmlBody;

    /**
     * Create a new message instance.
     */
    public function __construct($subjectText, $htmlBody)
    {
        $this->subjectText = $subjectText;
        $this->htmlBody = $htmlBody;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
