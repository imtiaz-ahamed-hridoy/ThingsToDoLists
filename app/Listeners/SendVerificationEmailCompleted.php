<?php

namespace App\Listeners;

use App\Events\EmailVerified;
use App\Notifications\EmailVerificationSuccessNotification;

class SendVerificationEmailCompleted
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EmailVerified $event): void
    {
        // Send notification to the user after email verification
        $event->user->notify(new EmailVerificationSuccessNotification);
    }
}
