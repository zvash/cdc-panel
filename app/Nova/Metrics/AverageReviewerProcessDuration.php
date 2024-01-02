<?php

namespace App\Nova\Metrics;

use App\Models\AppraisalJobChangeLog;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Nova;

class AverageReviewerProcessDuration extends Trend
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query = AppraisalJobChangeLog::query()->fromSub(function ($query) {
            $query->from('appraisal_job_change_logs')
                ->selectRaw('
                    appraisal_job_id,
                    sum(duration) as duration,
                    max(appraisal_job_change_logs.updated_at) updated_at
                ')
                ->join('appraisal_jobs', 'appraisal_jobs.id', '=', 'appraisal_job_change_logs.appraisal_job_id')
                ->where('action', 'submitted for review')
                ->whereNotNull('appraisal_jobs.completed_at')
                ->groupBy('appraisal_job_id');
        }, 'appraisal_job_change_logs');
        return $this->averageByDays($request, $query, 'duration', 'updated_at')
            ->transform(function ($value) {
                return intval($value / 60);
            })
            ->suffix('minute');
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
        return 'average-reviewer-process-duration';
    }
}
