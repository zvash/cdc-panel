<?php

namespace App\Mail;

use App\Models\AppraisalJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompletedJobMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var AppraisalJob $appraisalJob
     */
    protected $appraisalJob;

    /**
     * @var string $clientName
     */
    protected $clientName;

    /**
     * @var string $clientEmail
     */
    protected $clientEmail;

    /**
     * @var array $files
     */
    protected $files;

    /**
     * Create a new message instance.
     */
    public function __construct(AppraisalJob $appraisalJob, string $clientName, string $clientEmail, array $files = [])
    {
        $this->appraisalJob = $appraisalJob;
        $this->clientName = $clientName;
        $this->clientEmail = $clientEmail;
        $this->files = $files;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = 'Appraisal Job for Address: ' . $this->appraisalJob->property_address;
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mailable.job-notice',
            with: [
                'appraisalJob' => $this->appraisalJob,
                'name' => $this->clientName,
                'content' => 'Attached please find the completed appraisal job for the property located at ' . $this->appraisalJob->property_address . '. Please do let us know if you have any questions.',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->files as $file) {
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('s3', $file);
        }
        return $attachments;
    }
}
