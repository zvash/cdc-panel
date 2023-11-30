<?php

namespace App\Nova\Lenses;

use App\Enums\AppraisalJobStatus;
use App\Nova\Lenses\Traits\AppraisalJobLensIndex;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;

class OnHoldAppraisalJobs extends Lens
{
    use AppraisalJobLensIndex;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [];

    /**
     * Indicates whether the lens should automatically poll for new records.
     *
     * @var bool
     */
    public static $polling = true;

    /**
     * The interval (in seconds) at which Nova should poll for new lens.
     *
     * @var int
     */
    public static $pollingInterval = 10;

    /**
     * Indicates whether to show the polling toggle button inside Nova.
     *
     * @var bool
     */
    public static $showPollingToggle = true;

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param \Laravel\Nova\Http\Requests\LensRequest $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    public static function query(LensRequest $request, $query)
    {
        return $request->withOrdering($request->withFilters(
            $query->whereIn('status', [
                AppraisalJobStatus::InProgress,
                AppraisalJobStatus::Pending,
            ])
                ->where('is_on_hold', true)
                ->where(function ($query) use ($request) {
                    $user = $request->user();
                    if ($user->hasManagementAccess()) {
                        return $query;
                    }
                    return $query->where('appraiser_id', $user->id);
                })
        ));
    }

    public function name()
    {
        return 'On Hold Appraisals';
    }

    /**
     * Get the cards available on the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available on the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return parent::actions($request);
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'on-hold-appraisal-jobs';
    }
}
