<?php

namespace App\Nova\Lenses;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Enums\AppraisalJobStatus;
use App\Nova\Actions\AssignAppraiserAction;
use App\Nova\Lenses\Traits\AppraisalJobLensIndex;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;
use Lupennat\BetterLens\BetterLens;

class NotAssignedAppraisalJobs extends Lens
{
    use AppraisalJobLensIndex, BetterLens;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [];

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param \Laravel\Nova\Http\Requests\LensRequest $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    public static function query(LensRequest $request, $query)
    {
        if (!$request->user()->hasManagementAccess()) {
            return $query->where('id', 0);
        }
        return $request->withOrdering($request->withFilters(
            $query->where('status', AppraisalJobStatus::Pending)
                ->whereNull('appraiser_id')
                ->whereDoesntHave('assignments', function ($query) {
                    $query->whereIn('status', [
                        AppraisalJobAssignmentStatus::Pending,
                        AppraisalJobAssignmentStatus::Accepted,
                    ]);
                })
        ));
    }

    public function name()
    {
        return __('nova.lenses.not_assigned_appraisal_jobs.name');
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
        return 'not-assigned-appraisal-jobs';
    }
}
