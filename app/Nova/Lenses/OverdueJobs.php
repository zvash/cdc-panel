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
use Lupennat\BetterLens\BetterLens;

class OverdueJobs extends Lens
{
    use AppraisalJobLensIndex, BetterLens;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'appraisalType.name',
        'office.city',
        'property_address',
        'appraiser.name',
        'reference_number',
    ];

    public static function withRelated()
    {
        return [
            'appraisalType',
            'office',
            'appraiser',
        ];
    }

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
            $query->whereNotIn('status', [AppraisalJobStatus::Cancelled, AppraisalJobStatus::Completed])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
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
        return __("nova.lenses.overdue_jobs.name");
    }

    /**
     * Get the cards available on the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available on the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
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
        return 'overdue-jobs';
    }
}
