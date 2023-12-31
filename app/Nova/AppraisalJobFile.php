<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\File;

class AppraisalJobFile extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\AppraisalJobFile>
     */
    public static $model = \App\Models\AppraisalJobFile::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToDelete(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        return $user->hasManagementAccess()
            || $user->id = $this->user_id
                || (
                    $this->appraisalJob->appraiser_id
                    && \App\Models\User::query()
                        ->where('id', $this->appraisalJob->appraiser_id)
                        ->whereJsonContains('reviewers', "{$user->id}")
                        ->exists()
                );

    }

    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Uploader', 'user', \App\Nova\User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            BelongsTo::make('Appraisal Job', 'appraisalJob', \App\Nova\AppraisalJob::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($appraisalJob) {
                    return $appraisalJob->id;
                }),

            Text::make('File', 'id')
                ->onlyOnIndex()
                ->displayUsing(function ($id) {
                    return "<a class='link-default' href='/download-job-file/{$id}' target='_blank'>Download</a>";
                })->asHtml(),

            File::make('File')
                ->disk('local')
                ->path('appraisal-job-files')
                ->displayUsing(function ($file) {
                    return explode('/', $file)[1];
                })
                ->onlyOnDetail(),

            Text::make('Comment')
                ->nullable()
                ->exceptOnForms()
                ->rules('nullable', 'string', 'max:255'),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
