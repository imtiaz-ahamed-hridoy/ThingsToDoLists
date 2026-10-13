<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

class EmailVerificationSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        $this->onQueue('email');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email Verification Successful')
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('Thank you for verifying your email address.')
            ->line('Your account is now fully activated, and you can access all features of our platform.')
            ->line('We appreciate your trust in our platform.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => 'Your email has been successfully verified. Thank you!',
            'user_id' => $notifiable->id,
        ];
    }
}
