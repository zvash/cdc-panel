<?php

namespace App\Providers;

use App\Nova\AppraisalJob;
use App\Models\User;
use App\Nova\Client;
use App\Nova\Invitation;
use App\Nova\Lenses\AssignedAppraisalJobs;
use App\Nova\Lenses\CompletedAppraisalJobs;
use App\Nova\Lenses\InProgressAppraisalJobs;
use App\Nova\Lenses\InReviewAppraisalJobs;
use App\Nova\Lenses\NotAssignedAppraisalJobs;
use App\Nova\Lenses\OnHoldAppraisalJobs;
use App\Nova\Office;
use App\Observers\AppraisalJobObserver;
use App\Observers\UserObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        \App\Models\User::observe(UserObserver::class);
        \App\Models\AppraisalJob::Observe(AppraisalJobObserver::class);

        Nova::withBreadcrumbs();

        Nova::initialPath('/resources/appraisal-jobs/lens/pending-appraisal-jobs');

        Nova::userTimezone(function (Request $request) {
            return $request->user()?->timezone;
        });

        Nova::mainMenu(fn($request) => [
            MenuSection::make('Clients', [
                MenuItem::resource(Client::class),
            ])->icon('user-group'),

            MenuSection::make('Offices', [
                MenuItem::resource(Office::class),
            ])->icon('office-building'),

            MenuSection::make('Appraisal Jobs', [
                MenuItem::resource(AppraisalJob::class),
                MenuItem::lens(AppraisalJob::class, NotAssignedAppraisalJobs::class)
                    ->canSee(function ($request) {
                        return $request->user()->hasManagementAccess();
                    }),
                MenuItem::lens(AppraisalJob::class, AssignedAppraisalJobs::class),
                MenuItem::lens(AppraisalJob::class, InProgressAppraisalJobs::class),
                MenuItem::lens(AppraisalJob::class, CompletedAppraisalJobs::class),

            ])->icon('clipboard-list'),

            MenuSection::make('Need Action', [
                MenuItem::lens(AppraisalJob::class, InReviewAppraisalJobs::class),
                MenuItem::lens(AppraisalJob::class, OnHoldAppraisalJobs::class),

            ])->icon('eye'),

            MenuSection::make('Accounts', [
                MenuItem::resource(\App\Nova\User::class),
                MenuItem::resource(Invitation::class),
            ])->icon('cog'),
        ]);

        Nova::footer(function ($request) {
            return Blade::render('');
        });

        Nova::style('nova-logo', asset('css/nova-logo.css'));
    }

    /**
     * Register the Nova routes.
     *
     * @return void
     */
    protected function routes()
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNova', function ($user) {
            return in_array($user->email, [
                //
            ]);
            //All users with a role
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array
     */
    protected function dashboards()
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
