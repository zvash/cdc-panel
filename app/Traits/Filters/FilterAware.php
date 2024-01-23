<?php

namespace App\Traits\Filters;

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
        return $filters;
    }

    private function filterColumn(string $key): string
    {
        Log::info($this->name(), ['key' => $key]);
        return match ($key) {
            'CreatedAfter', 'CreatedBefore' => 'created_at',
            'CompletedAfter', 'CompletedBefore' => 'completed_at',
            'InProvince' => 'office.province',
            'OfficeFilter' => 'office_id',
            'AppraisalTypeFilter' => 'appraisal_type_id',
            'ClientFilter' => 'client_id',
            'AppraiserFilter' => 'appraiser_id',
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
            //Log::info($this->name(), ['key' => $key, 'column' => $column, 'value' => $value, 'res' => $query->toSql()]);
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
}
