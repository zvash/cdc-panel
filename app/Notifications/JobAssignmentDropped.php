<?php

namespace App\Notifications;

use App\Models\AppraisalJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Nova\Nova;

class JobAssignmentDropped extends Notification
{
    use Queueable;

    private $appraisalJob;

    private $appraiser;

    /**
     * Create a new notification instance.
     */
    public function __construct(AppraisalJob $appraisalJob, User $appraiser)
    {
        $this->appraisalJob = $appraisalJob;
        $this->appraiser = $appraiser;
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
        $creator = User::query()->find($this->appraisalJob->created_by);
        return (new MailMessage)
            ->subject('Job Assignment Declined (Action Required)')
            ->greeting("Hello $creator->name!")
            ->line("{$this->appraiser->name} has declined the assignment for \"{$this->appraisalJob->property_address}\"!")
            ->line('Please click the link below to view the job details.')
            ->action('Click here to view the job.', $this->generateJobUrl());

    }

    private function generateJobUrl()
    {
        $url = rtrim(env('APP_URL'), '/') . Nova::URL('/resources/appraisal-jobs/') . $this->appraisalJob->id;
        return $url;
    }
}
