<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class AddFile extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * Perform the action on the given models.
     *
     * @param \Laravel\Nova\Fields\ActionFields $fields
     * @param \Illuminate\Support\Collection $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $appraisalJob = \App\Models\AppraisalJob::query()->find($models->first()->id);
        /** @var UploadedFile $file */
        $file = $fields->file;
        $fileName = mt_rand(1000000, 9999999) . '-' . $file->getClientOriginalName();
        $path = $file->storeAs('appraisal-job-files', $fileName, ['disk' => 'local']);
        $appraisalJob->files()->create([
            'user_id' => auth()->user()->id,
            'appraisal_job_id' => $appraisalJob->id,
            'file' => $path,
            'comment' => $fields->comment ?? null,
        ]);
        return Action::message('File has been added.');
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            File::make('File')
                ->required()
                ->rules('required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt,csv,jpg,jpeg,png,svg,webp'),

            Text::make('Comment')
                ->nullable()
                ->rules('nullable', 'string', 'max:255'),

        ];
    }
}
