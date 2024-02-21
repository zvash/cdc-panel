<?php

namespace App\Nova\Actions;

use App\Mail\CompletedJobMail;
use App\Models\AppraisalJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MailCompletedJob extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * @var AppraisalJob $appraialJob
     */
    protected $appraialJob;

    public function __construct(AppraisalJob $appraisalJob)
    {
        $this->appraisalJob = $appraisalJob;
    }

    public function name()
    {
        return 'Send To Client';
    }

    /**
     * Perform the action on the given models.
     *
     * @param \Laravel\Nova\Fields\ActionFields $fields
     * @param \Illuminate\Support\Collection $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $this->appraisalJob = $models->first();
        $clientName = $fields->client_name;
        $clientEmail = $fields->client_email;
        $files = [];
        foreach ($fields->files as $path => $exists) {
            if (!$exists) {
                continue;
            }
            $files[] = $path;
        }
        $mail = new CompletedJobMail($this->appraisalJob, $clientName, $clientEmail, $files);
        Mail::to($clientEmail)->send($mail);
        return Action::message('Mail Sent!');
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $client = $this->appraisalJob?->client;
        $defaultName = $client?->name;
        $defaultEmail = $client?->email;
        $files = [];
        foreach ($this->appraisalJob->media as $media) {
            $files[$media->getPath()] = $media->name;
        }
        return [
            Text::make('Client Name', 'client_name')
                ->default($defaultName)
                ->rules('required', 'max:255')
                ->required(),
            Text::make('Client Email', 'client_email')
                ->default($defaultEmail)
                ->rules('email', 'required')
                ->required(),
            BooleanGroup::make('Files')
                ->options($files),
        ];
    }
}
