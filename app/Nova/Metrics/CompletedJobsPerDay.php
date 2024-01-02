<?php

namespace App\Nova\Metrics;

use App\Models\AppraisalJob;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Laravel\Nova\Nova;

class CompletedJobsPerDay extends Trend
{

    private $source = '';

    private $provinceId = 0;

    private $provinceName = '';

    public function setProvince($provinceId, $provinceName)
    {
        $this->provinceId = $provinceId;
        $this->provinceName = $provinceName;
        return $this;
    }

    /**
     * @param string $source
     */
    public function setSource(string $source)
    {
        $this->source = $source;
        return $this;
    }
    /**
     * Calculate the value of the metric.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query = AppraisalJob::query()->whereNotNull('completed_at');
        if ($this->source && $request->resourceId) {
            $query->where($this->source, $request->resourceId);
        }
        if ($this->provinceId) {
            $query->join('offices', 'offices.id', '=', 'appraisal_jobs.office_id')
                ->where('offices.province', $this->provinceName);
        }
        return $this->countByDays($request, $query, 'completed_at')
            ->suffix('job');
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
            180 => Nova::__('6 Months'),
            365 => Nova::__('1 Year'),
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
        if ($this->provinceName) {
            return 'Completed Jobs Per Day in ' . $this->provinceName;
        }
        return 'Completed Jobs Per Day';
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'completed-job-per-day';
    }
}
