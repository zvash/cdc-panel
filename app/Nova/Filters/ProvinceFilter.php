<?php

namespace App\Nova\Filters;

use App\Models\Office;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ProvinceFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

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
        $officeIds = [0];
        $officeIds = array_merge($officeIds,
            Office::query()->where('province', $value)->pluck('id')->toArray()
        );

        return $query->whereRaw('office_id in ('. implode(',', $officeIds) .')');
    }

    /**
     * Get the filter's available options.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return \App\Models\Province::query()
            ->whereRaw('name in (select province from offices)')
            ->pluck('name', 'name')->toArray();
    }

    public function name()
    {
        return 'In Province';
    }
}
