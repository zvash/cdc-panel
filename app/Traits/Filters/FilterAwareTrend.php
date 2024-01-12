<?php

namespace App\Traits\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Nova\Metrics\TrendDateExpressionFactory;
use Laravel\Nova\Nova;

trait FilterAwareTrend
{
    protected function aggregate($request, $model, $unit, $function, $column, $dateColumn = null)
    {
        $query = $model instanceof Builder ? $model : (new $model)->newQuery();

        $timezone = Nova::resolveUserTimezone($request) ?? $this->getDefaultTimezone($request);

        $expression = (string)TrendDateExpressionFactory::make(
            $query, $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn(),
            $unit, $timezone
        );

        $boundary = $this->getTimeBoundary($timezone);
        $possibleDateResults = $this->getAllPossibleDateResults(
            $startingDate = $this->getAggregateStartingDate($request, $unit, $timezone, $boundary),
            $endingDate = $boundary ? $boundary[1] : CarbonImmutable::now($timezone),
            $unit,
            $request->twelveHourTime === 'true',
            $request->range
        );

        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);
        $query = $query
            ->select(DB::raw("{$expression} as date_result, {$function}({$wrappedColumn}) as aggregate"))
            ->tap(function ($query) use ($request) {
                return $this->applyFilterQuery($request, $query);
            });
        if (!$this->hasTimeBoundFilter()) {
            $query = $query->whereBetween(
                $dateColumn, $this->formatQueryDateBetween([$startingDate, $endingDate])
            );
        }
        $results = $query
            ->groupBy(DB::raw($expression))
            ->orderBy('date_result')
            ->get();

        $possibleDateKeys = array_keys($possibleDateResults);

        $results = array_merge($possibleDateResults, $results->mapWithKeys(function ($result) use ($request, $unit) {
            return [$this->formatAggregateResultDate(
                $result->date_result, $unit, $request->twelveHourTime === 'true'
            ) => round($result->aggregate, $this->roundingPrecision, $this->roundingMode)];
        })->reject(function ($value, $key) use ($possibleDateKeys) {
            return !in_array($key, $possibleDateKeys);
        })->all());

        return $this->result(Arr::last($results))->trend(
            $results
        );
    }

    protected function getAggregateStartingDate($request, $unit, $timezone, $boundary = null)
    {
        $now = CarbonImmutable::now($timezone);
        $range = $request->range ?? 1;
        $ranges = collect($this->ranges())->keys()->values()->all();

        if (count($ranges) > 0 && !in_array($range, $ranges)) {
            $range = min($range, max($ranges));
        }

        if ($boundary) {
            $now = $boundary[1];
            $range = $now->diffInDays($boundary[0]);
        }


        switch ($unit) {
            case 'month':
                return $now->subMonthsWithoutOverflow($range - 1)->firstOfMonth()->setTime(0, 0);

            case 'week':
                return $now->subWeeks($range - 1)->startOfWeek()->setTime(0, 0);

            case 'day':
                return $now->subDays($range - 1)->setTime(0, 0);

            case 'hour':
                return with($now->subHours($range - 1), function ($now) {
                    return $now->setTimeFromTimeString($now->hour . ':00');
                });

            case 'minute':
                return with($now->subMinutes($range - 1), function ($now) {
                    return $now->setTimeFromTimeString($now->hour . ':' . $now->minute . ':00');
                });

            default:
                throw new InvalidArgumentException('Invalid trend unit provided.');
        }
    }

    private function getDefaultTimezone($request)
    {
        return $request->timezone ?? config('app.timezone');
    }
}
