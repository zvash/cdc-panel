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
    /**
     * @param mixed $boundary
     * @param mixed $now
     * @param $range
     * @param $unit
     * @return array
     */
    public function getNowAndRange(mixed $boundary, mixed $now, $range, $unit): array
    {
        if ($boundary) {
            $now = $boundary[1];
            $range = $now->diffInDays($boundary[0]);
            match ($unit) {
                'month' => $range = $now->lastOfMonth()->diffInMonths($boundary[0]) + 1,
                'week' => $range = $now->diffInWeeks($boundary[0]) + 1,
                'day' => $range = $now->diffInDays($boundary[0]) + 1,
                'hour' => $range = $now->diffInHours($boundary[0]) + 1,
                'minute' => $range = $now->diffInMinutes($boundary[0]) + 1,
            };
        }
        return array($now, $range);
    }

    protected function aggregate($request, $model, $unit, $function, $column, $dateColumn = null)
    {
        $query = $model instanceof Builder ? $model : (new $model)->newQuery();

        $timezone = Nova::resolveUserTimezone($request) ?? $this->getDefaultTimezone($request);

        $queryCopy = clone $query;
        $minDate = $queryCopy->min($dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn());

        $expression = (string)TrendDateExpressionFactory::make(
            $query, $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn(),
            $unit, $timezone
        );
        $boundary = $this->getTimeBoundary($timezone, $minDate);
        list($now, $range) = $this->getNowAndRange($boundary, $boundary[1], $request->range ?? 1, $unit);
        $possibleDateResults = $this->getAllPossibleDateResults(
            $startingDate = $this->getAggregateStartingDate($request, $unit, $timezone, $boundary),
            $endingDate = $boundary ? $boundary[1] : CarbonImmutable::now($timezone),
            $unit,
            $request->twelveHourTime === 'true',
            $range
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

        $possibleDateKeys = array_keys($possibleDateResults);

        $results = $query
            ->groupBy(DB::raw($expression))
            ->orderBy('date_result')
            ->get();




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

        list($now, $range) = $this->getNowAndRange($boundary, $now, $range, $unit);


        switch ($unit) {
            case 'month':
                return $now->copy()->lastOfMonth()->subMonthsWithoutOverflow($range - 1)->firstOfMonth()->setTime(0, 0);

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

    private function getTimeBoundary($timezone, $minDate, ?array $filters = null): ?array
    {
        if (!$minDate) {
            $minDate = CarbonImmutable::now($timezone)->subMonths(2);
        } else {
            $minDate = CarbonImmutable::make($minDate)->subMonths(2);
        }
        $boundary = [
            $minDate,
            CarbonImmutable::now($timezone),
        ];
        if ($filters === null) {
            $filters = $this->extractFilters(request());
        }
        if (!$this->hasTimeBoundFilter($filters)) {
            return $boundary;
        }
        if (isset($filters['CreatedAfter'])) {
            $boundary[0] = max($boundary[0], \Carbon\Carbon::make($filters['CreatedAfter']));
        }
        if (isset($filters['CompletedAfter'])) {
            $boundary[0] = max($boundary[0], \Carbon\Carbon::make($filters['CompletedAfter']));
        }
        if (isset($filters['CreatedBefore'])) {
            $boundary[1] = min($boundary[1], \Carbon\Carbon::make($filters['CreatedBefore']));
        }
        if (isset($filters['CompletedBefore'])) {
            $boundary[1] = min($boundary[1], \Carbon\Carbon::make($filters['CompletedBefore']));
        }
        return $boundary;
    }
}
