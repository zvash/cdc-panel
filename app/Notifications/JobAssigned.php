<?php

namespace App\Notifications;

use App\Models\AppraisalJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Laravel\Nova\Nova;

class JobAssigned extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    private $appraisalJob;

    /**
     * Create a new message instance.
     */
    public function __construct(AppraisalJob $appraisalJob)
    {
        $this->appraisalJob = $appraisalJob;
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

    public function toMail(object $notifiable): MailMessage
    {
        $appName = env('APP_NAME');

        return (new MailMessage())
            ->subject("{$appName} Job Assigned (Action Required)")
            ->greeting("Hello $notifiable->name!")
            ->line("You have been assigned a new job in the {$appName} Appraisal Management System!")
            ->line('Please click the link below to view the job and accept or decline the assignment.')
            ->action('Click here to view the job.', $this->generateJobUrl());
    }

    private function generateJobUrl()
    {
        $url = rtrim(env('APP_URL'), '/') . Nova::URL('/resources/appraisal-jobs/') . $this->appraisalJob->id;
        return $url;
    }

}
