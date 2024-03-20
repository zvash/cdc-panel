<?php

namespace App\Notifications;

use App\Models\AppraisalJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Nova\Nova;

class JobAssignedNoAction extends Notification implements ShouldQueue
{
    use Queueable;

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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = env('APP_NAME');
        $url = $this->generateJobUrl();
        $lines = [
            "You have been assigned a new job in the {$appName} space!",
            'Please click the link below to view the job details and start working on the assignment.'
        ];
        return (new MailMessage())
            ->subject("{$appName} Job Assigned")
            ->view('mailable.job', [
                'url' => $url,
                'notifiable' => $notifiable,
                'content' => implode(' ', $lines),
                'title' => "View Job",
            ]);
    }

    private function generateJobUrl()
    {
        $url = rtrim(config('app.url', 'https://cdcinc.space'), '/') . Nova::URL('/resources/appraisal-jobs/') . $this->appraisalJob->id;
        return $url;
    }
}
