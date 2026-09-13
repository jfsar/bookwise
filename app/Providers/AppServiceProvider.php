<?php

namespace App\Providers;

use App\Listeners\UpdateLastLogin;
use App\Models\Account;
use App\Models\ReportExport;
use App\Observers\AccountObserver;
use App\Policies\ReportPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(ReportExport::class, ReportPolicy::class);

        Account::observe(AccountObserver::class);

        Event::listen(Login::class, UpdateLastLogin::class);
    }
}
