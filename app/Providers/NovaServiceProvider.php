<?php

namespace App\Providers;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJobAssignment;
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
use App\Nova\Lenses\RejectedAppraisalJobs;
use App\Nova\Office;
use App\Observers\AppraisalJobAssignmentObserver;
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
        \App\Models\AppraisalJobAssignment::Observe(AppraisalJobAssignmentObserver::class);

        Nova::withoutThemeSwitcher();

        Nova::withBreadcrumbs();

        Nova::initialPath('/resources/appraisal-jobs/lens/pending-appraisal-jobs');

        Nova::userTimezone(function (Request $request) {
            return $request->user()?->timezone;
        });

        Nova::mainMenu(fn($request) => [

            MenuSection::make('Appraisal Jobs', [
                MenuItem::resource(AppraisalJob::class),
                MenuItem::lens(AppraisalJob::class, AssignedAppraisalJobs::class)
                    ->withBadge(function () use ($request) {
                        if (!$request->user()) {
                            return 0;
                        }
                        if ($request->user()->hasManagementAccess()) {
                            return \App\Models\AppraisalJob::where('status', AppraisalJobStatus::Assigned)->count();
                        }
                        return \App\Models\AppraisalJob::query()
                            ->where('status', AppraisalJobStatus::Assigned)
                            ->whereHas('assignments', function ($query) use ($request) {
                                $query->where('appraiser_id', $request->user()->id)
                                    ->where('status', AppraisalJobAssignmentStatus::Pending);
                            })->count();
                    }),
                MenuItem::lens(AppraisalJob::class, InProgressAppraisalJobs::class),
                MenuItem::lens(AppraisalJob::class, CompletedAppraisalJobs::class),

            ])->icon('clipboard-list'),

            MenuSection::make('Need Action', [
                MenuItem::lens(AppraisalJob::class, NotAssignedAppraisalJobs::class)
                    ->canSee(function ($request) {
                        return $request->user()->hasManagementAccess();
                    })
                    ->withBadge(function () use ($request) {
                        if (!$request->user()) {
                            return 0;
                        }
                        return \App\Models\AppraisalJob::where('status', AppraisalJobStatus::Pending)->count();
                    }),
                MenuItem::lens(AppraisalJob::class, RejectedAppraisalJobs::class)
                    ->canSee(function ($request) {
                        return $request->user()->hasManagementAccess();
                    })
                    ->withBadge(function () use ($request) {
                        if (!$request->user()) {
                            return 0;
                        }
                        if ($request->user()->hasManagementAccess()) {
                            return \App\Models\AppraisalJob::query()
                                ->where('status', AppraisalJobStatus::InProgress)
                                ->whereNotNull('reviewer_id')
                                ->count();
                        }
                        return \App\Models\AppraisalJob::query()
                            ->where('status', AppraisalJobStatus::InProgress)
                            ->whereNotNull('reviewer_id')
                            ->where('appraiser_id', $request->user()->id)
                            ->count();
                    }, 'danger'),
                MenuItem::lens(AppraisalJob::class, InReviewAppraisalJobs::class)
                    ->withBadge(function () use ($request) {
                        if (!$request->user()) {
                            return 0;
                        }
                        if ($request->user()->hasManagementAccess()) {
                            return \App\Models\AppraisalJob::query()
                                ->where('status', AppraisalJobStatus::InReview)
                                ->count();
                        }
                        $user = $request->user();
                        return \App\Models\AppraisalJob::query()
                            ->where('status', AppraisalJobStatus::InReview)
                            ->whereHas('appraiser', function ($query) use ($user) {
                                return $query->whereJsonContains('reviewers', "{$user->id}");
                            })->count();
                    }, 'info'),
                MenuItem::lens(AppraisalJob::class, OnHoldAppraisalJobs::class)
                    ->withBadge(function () use ($request) {
                        if (!$request->user()) {
                            return 0;
                        }
                        if ($request->user()->hasManagementAccess()) {
                            return \App\Models\AppraisalJob::query()
                                ->where('is_on_hold', true)
                                ->count();
                        }
                        $user = $request->user();
                        return \App\Models\AppraisalJob::query()
                            ->where('is_on_hold', true)
                            ->where('appraiser_id', $user->id)
                            ->count();
                    }, 'warning'),

            ])->icon('eye'),

            MenuSection::make('Invoices', [
                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\AppraiserInvoice::class),
                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\AppraiserMonthlyInvoice::class),
                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\ClientInvoice::class)
                    ->canSee(function ($request) {
                        return $request->user()->hasManagementAccess();
                    }),
                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\ClientMonthlyInvoice::class)
                    ->canSee(function ($request) {
                        return $request->user()->hasManagementAccess();
                    }),
            ])->icon('currency-dollar'),

            MenuSection::make('Settings', [
                MenuItem::resource(\App\Nova\User::class),
                MenuItem::resource(\App\Nova\ProvinceTax::class),
                MenuItem::resource(\App\Nova\Province::class),
                MenuItem::resource(\App\Nova\City::class),
            ])->icon('cog'),

            MenuSection::make('Clients', [
                MenuItem::resource(Client::class),
            ])->icon('user-group'),

            MenuSection::make('Offices', [
                MenuItem::resource(Office::class),
            ])->icon('office-building'),
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
