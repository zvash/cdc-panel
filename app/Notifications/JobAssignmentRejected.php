<?php

namespace App\Notifications;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Models\AppraisalJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Nova\Nova;

class JobAssignmentRejected extends Notification
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
        $subject = 'Job Assignment Declined';
        $hasChanceToBePickedUp = $this->appraisalJob
            ->assignments()
            ->whereIn('status', [AppraisalJobAssignmentStatus::Accepted->value, AppraisalJobAssignmentStatus::Pending->value])
            ->exists();
        if (!$hasChanceToBePickedUp) {
            $subject .= ' (Action Required)';
        }
        return (new MailMessage)
            ->subject($subject)
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
