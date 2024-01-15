<?php

namespace App\Nova\Metrics;

use App\Models\AppraisalJobChangeLog;
use App\Traits\Filters\FilterAware;
use App\Traits\Filters\FilterAwareTrend;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Laravel\Nova\Nova;

class AverageResponseTime extends Trend
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
        $filters = $this->extractFilters($request);
        $query = AppraisalJobChangeLog::query()->fromSub(function ($query) {
            $query->from('appraisal_job_change_logs')
                ->selectRaw('
                    id,
                    user_id,
                    user_id as appraiser_id,
                    action,
                    duration,
                    updated_at,
                    created_at
                ');
        }, 'appraisal_job_change_logs')
            ->whereIn('action', ['accepted', 'declined']);
        if ($request->resourceId) {
            $query->where('user_id', $request->resourceId);
        }
        if ($request->user()->isAppraiser()) {
            $query->where('user_id', $request->user()->id);
        }

        $query = $this->applyFilter($query, $filters, [
            'created_at',
            'appraiser_id',
        ]);

        return $this->averageByDays($request, $query, 'duration', 'updated_at')
            ->transform(function ($value) {
                return intval($value / 60);
            })->suffix('minute');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            1 => Nova::__('Today'),
            2 => Nova::__('2 Days'),
            3 => Nova::__('3 Days'),
            7 => Nova::__('1 Week'),
            14 => Nova::__('2 Weeks'),
            21 => Nova::__('3 Weeks'),
            30 => Nova::__('1 Month'),
            60 => Nova::__('2 Months'),
            90 => Nova::__('3 Months'),
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

    public function name()
    {
        if (request()->resourceId) {
            return 'Average Response Time';
        }
        return 'Overall Average Response Time';
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'average-response-time';
    }
}
