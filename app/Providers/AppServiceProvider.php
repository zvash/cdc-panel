<?php

namespace App\Providers;

use App\Observers\AppraisalJobObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\AppraisalJob::Observe(AppraisalJobObserver::class);
    }
}
