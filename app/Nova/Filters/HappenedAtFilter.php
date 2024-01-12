<?php

namespace App\Nova\Filters;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class HappenedAtFilter extends DateFilter
{
    private $operator = '';
    private $column = 'created_at';

    public function __construct(string $operator, string $column = 'created_at')
    {
        $this->operator = $operator;
        $this->column = $column;
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
        $value = Carbon::parse($value);

        return $query->where($this->column, $this->operator . $value);
    }

    public function name()
    {
        if ($this->column === 'created_at') {
            if ($this->operator == '>=') {
                return 'Created After';
            } elseif ($this->operator == '<=') {
                return 'Created Before';
            }
            return 'Created On';
        } elseif ($this->column === 'completed_at') {
            if ($this->operator == '>=') {
                return 'Completed After';
            } elseif ($this->operator == '<=') {
                return 'Completed Before';
            }
            return 'Completed On';
        }
        return 'Happened On';
    }

    public function key()
    {
        return ucfirst(Str::camel($this->name()));
    }
}
