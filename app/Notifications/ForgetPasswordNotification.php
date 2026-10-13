<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgetPasswordNotification extends Notification
{
    /**
     * The OTP code.
     */
    protected string $otp;

    /**
     * The expiration time in minutes.
     */
    protected int $expiresIn;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, int $expiresIn = 10)
    {
        $this->otp = $otp;
        $this->expiresIn = $expiresIn;
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
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset OTP')
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Your password reset OTP is: ' . $this->otp)
            ->line("This OTP will expire in {$this->expiresIn} minutes.")
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp' => $this->otp,
            'expires_in' => $this->expiresIn,
            'user_id' => $notifiable->id,
        ];
    }
}
