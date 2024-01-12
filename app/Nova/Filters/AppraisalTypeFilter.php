<?php

namespace App\Nova\Filters;

use App\Models\AppraisalType;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class AppraisalTypeFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public function name()
    {
        return 'Appraisal Type';
    }

    /**
     * Apply the filter to the given query.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->where('appraisal_type_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return AppraisalType::query()->pluck('id', 'name')->toArray();;
    }
}
