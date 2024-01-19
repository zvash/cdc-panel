<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

class UserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
        $appName = env('APP_NAME');

        return (new MailMessage)
            ->subject("{$appName} Invitation")
            ->greeting('Hello!')
            ->line("You have been invited to join the {$appName} Appraisal Management System!")
            ->action('Click here to set your password.', $this->generateInvitationUrl($notifiable))
            ->line('Note: this link expires after 48 hours.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [

        ];
    }

    public function generateInvitationUrl($notifiable)
    {
        $token = Password::broker()->createToken($notifiable);
        return URL::temporarySignedRoute('nova.pages.password.reset', now()->addDays(2), [
            'email' => $notifiable->email,
            'token' => $token,
        ]);
    }
}
