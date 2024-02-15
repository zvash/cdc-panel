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
use App\Nova\Lenses\CanceledJobs;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Menu\Menu;
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
        //\App\Models\AppraisalJob::Observe(AppraisalJobObserver::class);
        \App\Models\AppraisalJobAssignment::Observe(AppraisalJobAssignmentObserver::class);

        Nova::withoutThemeSwitcher();

        Nova::withBreadcrumbs();

        Nova::serving(function (ServingNova $event) {
            /** @var \App\Models\User|null $user */
            $user = $event->request->user();

            if (is_null($user)) {
                return;
            }
            if (!$user->hasManagementAccess()) {
                Nova::initialPath('/resources/appraisal-jobs/lens/pending-appraisal-jobs');
            }
        });

        Nova::userTimezone(function (Request $request) {
            return $request->user()?->timezone;
        });

        Nova::userMenu(function (Request $request, Menu $menu) {
            return $menu
                ->append(MenuItem::externalLink('Help', 'mailto:hamid@offerland.ca'));
        });

        Nova::mainMenu(fn($request) => [

            MenuSection::dashboard(\App\Nova\Dashboards\Main::class)->canSee(function ($request) {
                return $request->user()->hasManagementAccess();
            })->icon('chart-bar'),

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
                MenuItem::lens(AppraisalJob::class, CanceledJobs::class)
                    ->withBadge(function () use ($request) {
                        if (!$request->user()) {
                            return 0;
                        }
                        if ($request->user()->hasManagementAccess()) {
                            return \App\Models\AppraisalJob::where('status', AppraisalJobStatus::Cancelled)->count();
                        }
                        return \App\Models\AppraisalJob::query()
                            ->where('status', AppraisalJobStatus::Cancelled)->count();
                    }),

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
                        return \App\Models\AppraisalJob::where('status', AppraisalJobStatus::Pending)
                            ->whereNull('appraiser_id')->count();
                    }),
//                MenuItem::lens(AppraisalJob::class, RejectedAppraisalJobs::class)
//                    ->canSee(function ($request) {
//                        return $request->user()->hasManagementAccess();
//                    })
//                    ->withBadge(function () use ($request) {
//                        if (!$request->user()) {
//                            return 0;
//                        }
//                        if ($request->user()->hasManagementAccess()) {
//                            return \App\Models\AppraisalJob::query()
//                                ->where('status', AppraisalJobStatus::InProgress)
//                                ->whereNotNull('reviewer_id')
//                                ->count();
//                        }
//                        return \App\Models\AppraisalJob::query()
//                            ->where('status', AppraisalJobStatus::InProgress)
//                            ->whereNotNull('reviewer_id')
//                            ->where('appraiser_id', $request->user()->id)
//                            ->count();
//                    }, 'danger'),
                MenuItem::lens(AppraisalJob::class, InReviewAppraisalJobs::class)
                    ->canSee(function ($request) {
                        return $request->user()->isAppraiser();
                    })
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
                            ->where(function ($query) use ($user) {
                                return $query->where('reviewer_id', $user->id)
                                    ->orWhere(function ($query) use ($user) {
                                        return $query->whereNull('reviewer_id')
                                            ->whereHas('appraiser', function ($query) use ($user) {
                                                return $query->whereJsonContains('reviewers', "{$user->id}");
                                            });
                                    });
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
//                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\ClientInvoice::class)
//                    ->canSee(function ($request) {
//                        return $request->user()->hasManagementAccess();
//                    }),
                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\ClientMonthlyInvoice::class)
                    ->canSee(function ($request) {
                        return $request->user()->hasManagementAccess();
                    }),
                MenuItem::lens(AppraisalJob::class, \App\Nova\Lenses\MonthlyRevenueInvoice::class)
                    ->canSee(function ($request) {
                        return $request->user()->isSuperAdmin() || $request->user()->isSupervisor();
                    }),
            ])->icon('currency-dollar'),

            MenuSection::make('Settings', [
                MenuItem::resource(\App\Nova\User::class),
                MenuItem::resource(\App\Nova\ProvinceTax::class),
//                MenuItem::resource(\App\Nova\Province::class),
//                MenuItem::resource(\App\Nova\City::class),
            ])->icon('cog'),

            MenuSection::make('Clients', [
                MenuItem::resource(Client::class),
            ])->icon('user-group'),

            MenuSection::make('Offices', [
                MenuItem::resource(Office::class),
            ])->icon('office-building'),

            MenuSection::make('Support', [
                MenuItem::externalLink('Help', 'mailto:hamid@offerland.ca'),
            ])->icon('support'),
        ]);

        Nova::script('lock-light-theme', __DIR__ . '/../../public/theme.js');

        Nova::footer(function ($request) {
            return Blade::render('
            <p class="text-center">Powered by &nbsp;<a class="link-default" href="https://offerland.ca/" target="_blank">
            <img src="/storage/logo/svglogo.svg" width="90" style="display: inline">
</a> - {!! $year !!}</p>
        ', [
                'year' => date('Y'),
            ]);
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
