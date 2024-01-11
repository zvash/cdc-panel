<?php

namespace App\Nova\Dashboards;

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
        return [
            (new CompletedJobsPerDay())
                ->width('2/3')
                ->defaultRange('7')
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                })->refreshWhenFiltersChange(),
        ];
    }
}
