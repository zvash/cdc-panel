<?php

namespace App\Nova\Metrics;

use App\Models\AppraisalJob;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class JobPerStatus extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query = AppraisalJob::query()->fromSub(function ($query) {
            $query->from('appraisal_jobs')
                ->selectRaw('
                    id,
                    (CASE
                        WHEN is_on_hold THEN "On Hold"
                        ELSE status
                    END ) as status,
                    created_at
                ');
        }, 'appraisal_jobs')->whereRaw('created_at >= CAST(DATE_FORMAT(NOW() ,"%Y-%m-01") as DATE)');
        return $this->count($request, $query, 'status')
            ->colors([
                'On Hold' => 'rgb(137,137,137)',
                'Pending' => 'rgb(202,58,49)',
                'Assigned' => 'rgb(227,97,71)',
                'In Progress' => 'rgb(57,130,193)',
                'In Review' => 'rgb(239,199,82)',
                'Completed' => 'rgb(85,166,93)',
            ]);
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

    public function name()
    {
        return 'This Month Jobs Per Status';
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'job-per-status';
    }
}
