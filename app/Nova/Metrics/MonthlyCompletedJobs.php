<?php

namespace App\Nova\Metrics;

use App\Models\AppraisalJob;
use App\Traits\Filters\FilterAware;
use App\Traits\Filters\FilterAwareTrend;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Nova;

class MonthlyCompletedJobs extends Trend
{
    use FilterAware, FilterAwareTrend;

    /**
     * Calculate the value of the metric.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $rawQueryAsString = "
            SELECT
                appraisal_jobs.id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.created_at,
                appraisal_jobs.completed_at
            FROM
                appraisal_jobs
            WHERE
                completed_at IS NOT NULL
            AND
                appraiser_id IS NOT NULL
        ";
        $filters = $this->extractFilters($request);
        $query = AppraisalJob::query();
        $query->fromSub($rawQueryAsString, 'appraisal_jobs');
        $query = $this->applyFilter($query, $filters);
        return $this->countByMonths($request, $query, 'completed_at')
            ->suffix('job');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'monthly-completed-jobs';
    }
}
