<?php

namespace App\Nova\Lenses;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Nova\Actions\AssignAppraiserAction;
use App\Nova\Lenses\Traits\AppraisalJobLensIndex;
use App\Nova\User;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;
use Lupennat\BetterLens\BetterLens;

class AssignedAppraisalJobs extends Lens
{
    use AppraisalJobLensIndex, BetterLens;

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
    public static $showPollingToggle = false;

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
            $query->whereHas('assignments', function ($query) use ($request) {
                $user = $request->user();
                if (!$user->hasManagementAccess()) {
                    $query->where('status', AppraisalJobAssignmentStatus::Pending)
                        ->where('appraiser_id', $user->id);
                } else {
                    $query->where('status', AppraisalJobAssignmentStatus::Pending);
                }
            })
        ));
    }

    public function name()
    {
        $name = 'appraiser_name';
        if (request()->user()->hasManagementAccess()) {
            $name = 'admin_name';
        }
        return __("nova.lenses.assigned_appraisal_jobs.{$name}");
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
        return 'pending-appraisal-jobs';
    }
}
