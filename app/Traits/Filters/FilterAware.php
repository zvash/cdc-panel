<?php

namespace App\Traits\Filters;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

trait FilterAware
{
    public function extractFilters(Request $request): array
    {
        $refererURL = $request->headers->get('referer', '');
        if (!$refererURL) {
            return [];
        }
        $filters = [];
        $query = parse_url($refererURL, PHP_URL_QUERY);
        $parts = explode('&', $query);
        foreach ($parts as $part) {
            $section = explode('_', $part);
            foreach ($section as $entry) {
                if (Str::startsWith($entry, 'filter')) {
                    $filter = explode('=', $entry);
                    $extracted = stripslashes(stripslashes(base64_decode($filter[1])));
                    $extra = Str::afterLast($extracted, ']');
                    if ($extra) {
                        $extracted = substr($extracted, 0, -1 * strlen($extra));
                    }
                    $filtersAsJson = json_decode($extracted, true);
                    foreach ($filtersAsJson as $filterAsJson) {
                        foreach ($filterAsJson as $class => $value) {
                            if ($value === "") {
                                continue;
                            }
                            $class = str_replace('AppNovaFilters', '', $class);
                            $filters[$class] = $value;
                        }
                    }
                    break 2;
                }
            }
        }
        Log::info('Extracted filters', $filters);
        return $filters;
    }

    private function filterColumn(string $key): string
    {
        return match ($key) {
            'CreatedAfter', 'CreatedBefore' => 'created_at',
            'CompletedAfter', 'CompletedBefore' => 'completed_at',
            'InProvince' => 'office.province',
            'OfficeFilter' => 'office_id',
            'AppraisalTypeFilter' => 'appraisal_type_id',
            'ClientFilter' => 'client_id',
            default => '',
        };
    }

    private function applyFilter($query, $filters, $availableColumns = [])
    {
        foreach ($filters as $key => $value) {
            $column = $this->filterColumn($key);
            if (!$column) {
                continue;
            }
            if ($availableColumns && !in_array($column, $availableColumns)) {
                continue;
            }
            match ($key) {
                'CreatedAfter', 'CompletedAfter' => $query = $query->whereDate($column, '>=', $value),
                'CreatedBefore', 'CompletedBefore' => $query = $query->whereDate($column, '<=', $value),
                'InProvince' => $query = $query->whereRaw("office_id in (select id from offices where province = '$value')"),
                default => $query = $query->where($column, $value),
            };
        }
        return $query;
    }

    private function hasTimeBoundFilter(?array $filters = null): bool
    {
        if ($filters === null) {
            $filters = $this->extractFilters(request());
        }
        $timeFilters = ['CreatedAfter', 'CompletedAfter', 'CreatedBefore', 'CompletedBefore'];
        foreach ($timeFilters as $timeFilter) {
            if (isset($filters[$timeFilter])) {
                return true;
            }
        }
        return false;
    }

    private function getTimeBoundary($timezone, ?array $filters = null): ?array
    {
        $boundary = [
            CarbonImmutable::now($timezone)->subYears(10),
            CarbonImmutable::now($timezone),
        ];
        if ($filters === null) {
            $filters = $this->extractFilters(request());
        }
        if (!$this->hasTimeBoundFilter($filters)) {
            return null;
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
