<?php

namespace App\Nova\Dashboards;

use App\Nova\Filters\AppraisalTypeFilter;
use App\Nova\Filters\AppraiserFilter;
use App\Nova\Filters\ClientFilter;
use App\Nova\Filters\HappenedAtFilter;
use App\Nova\Filters\OfficeFilter;
use App\Nova\Filters\ProvinceFilter;
use App\Nova\Metrics\AverageJobCreationToCompletionDuration;
use App\Nova\Metrics\AverageResponseTime;
use App\Nova\Metrics\JobPerStatus;
use App\Nova\Metrics\MonthlyCompletedJobs;
use App\Nova\Metrics\MonthlyRevenue;
use DigitalCreative\NovaDashboard\Card\NovaDashboard;
use DigitalCreative\NovaDashboard\Card\View;
use App\Nova\Metrics\CompletedJobsPerDay;
use Laravel\Nova\Cards\Help;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    public function name()
    {
        return 'Analytics';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $request = request();

        $provinces = \App\Models\Province::query()
            ->whereRaw('name in (select province from offices)')
            ->pluck('name', 'id');
        $completedPerProvinces = [];
        foreach ($provinces as $provinceId => $provinceName) {
            $completedPerProvinces[] = (new CompletedJobsPerDay())
                ->width('1/3')
                ->setProvince($provinceId, $provinceName)
                ->canSee(function ($request) {
                    return $request->user()->hasManagementAccess();
                })
                ->defaultRange('7');
        }

        return [
            NovaDashboard::make()
                ->addView('Filters', function (View $view) use ($request) {
                    return $view
                        ->icon('window')
                        ->addWidgets([])
                        ->addFilters([
                            (new HappenedAtFilter('>=')),
                            (new HappenedAtFilter('<=')),
                            (new HappenedAtFilter('>=', 'completed_at')),
                            (new HappenedAtFilter('<=', 'completed_at')),
                            OfficeFilter::make(),
                            AppraisalTypeFilter::make(),
                            ProvinceFilter::make(),
                            ClientFilter::make(),
                            AppraiserFilter::make(),
                        ]);
                }),
            (new MonthlyRevenue())
                ->width('1/2')
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                })->refreshWhenFiltersChange(),
            (new MonthlyCompletedJobs())
                ->width('1/2')
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                })->refreshWhenFiltersChange(),
            (new CompletedJobsPerDay())
                ->width('2/3')
                ->defaultRange('7')
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                })->refreshWhenFiltersChange(),
            (new JobPerStatus())
                ->width('1/3')
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                })->refreshWhenFiltersChange(),
            (new AverageJobCreationToCompletionDuration())
                ->width('full')
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                })->refreshWhenFiltersChange(),
            (new AverageResponseTime())
                ->width('1/3')
                ->defaultRange('30')
                ->refreshWhenFiltersChange(),
            ...$completedPerProvinces,
        ];
    }
}
