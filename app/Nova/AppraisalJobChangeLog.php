<?php

namespace App\Nova;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class AppraisalJobChangeLog extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\AppraisalJobChangeLog>
     */
    public static $model = \App\Models\AppraisalJobChangeLog::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'action';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'action',
    ];

    public static function label()
    {
        return 'Log History';
    }

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
            BelongsTo::make('Appraisal Job', 'appraisalJob', AppraisalJob::class),
            BelongsTo::make('Changed By', 'user', User::class),
            Text::make('Action'),
            Text::make('Description')->asHtml(),
            DateTime::make('Changed At', 'created_at')
                ->displayUsing(function ($date) use ($request) {
                    return Carbon::parse($date)
                        ->setTimezone($request->user()->timezone)
                        ->format('Y-m-d H:i:s T');
                }),
            Text::make('Duration')
                ->displayUsing(function ($seconds) {
                    if ($seconds) {
                        return $this->secondsToHumanReadable($seconds);
                    }
                    return '-';
                }),
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

    private function secondsToHumanReadable($seconds)
    {
        $days = floor($seconds / 86400);
        $r = $seconds % 86400;
        $hours = floor($r / 3600);
        $r = $r % 3600;
        $minutes = floor($r / 60);
        $seconds = $r % 60;

        $result = '';

        if ($days > 0) {
            $result .= $days . 'd ';
        }

        if ($hours > 0) {
            $result .= $hours . 'h ';
        }

        if ($minutes > 0) {
            $result .= $minutes . 'm ';
        }

        if ($seconds > 0 || empty($result)) {
            $result .= $seconds . 's';
        }

        return $result;
    }
}
