<?php

namespace App\Nova\Metrics;

use App\Models\AppraisalJob;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Nova;

class AverageJobCreationToCompletionDuration extends Trend
{
    public function name()
    {
        return 'Average Appraisal Duration';
    }

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
                    appraiser_id,
                    office_id,
                    appraisal_type_id,
                    reference_number,
                    client_id,
                    created_at,
                    completed_at,
                    (UNIX_TIMESTAMP(completed_at) - UNIX_TIMESTAMP(created_at)) as duration
                ')->whereNotNull('completed_at');
        }, 'appraisal_jobs');
        return $this->averageByDays($request, $query, 'duration', 'completed_at')
            ->transform(function ($value) {
                return intval($value / 60 / 60);
            })
            ->suffix('hour');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            90 => Nova::__('90 Days'),
        ];
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
        return 'average-job-creation-to-completion-duration';
    }
}
