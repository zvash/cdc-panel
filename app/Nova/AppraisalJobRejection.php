<?php

namespace App\Nova;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class AppraisalJobRejection extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\AppraisalJobRejection>
     */
    public static $model = \App\Models\AppraisalJobRejection::class;

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
        return false;
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

            BelongsTo::make('Appraisal Job', 'appraisalJob', AppraisalJob::class)
                ->sortable(),

            BelongsTo::make('Rejected By', 'rejectedBy', User::class)
                ->sortable(),

            Text::make('Reason', 'reason')
                ->onlyOnIndex(),

            Textarea::make('Reason', 'reason'),

            DateTime::make('Rejected At', 'created_at')
                ->displayUsing(function ($date) use ($request) {
                    return Carbon::parse($date)
                        ->setTimezone($request->user()->timezone)
                        ->format('Y-m-d H:i:s T');
                })
                ->sortable(),

            Text::make('File', 'id')
                ->onlyOnIndex()
                ->displayUsing(function ($id) {
                    if (!$this->file) {
                        return '-';
                    }
                    return "<a class='link-default' href='/download-rejected-job-file/{$id}' target='_blank'>Download</a>";
                })->asHtml(),

            File::make('File')
                ->disk('media')
                ->path('review-rejected-files')
                ->displayUsing(function ($file) {
                    if (!$file) {
                        return '-';
                    }
                    return explode('/', $file)[1];
                })
                ->onlyOnDetail(),
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
