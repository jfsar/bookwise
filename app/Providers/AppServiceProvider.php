<?php

namespace App\Providers;

use App\Listeners\UpdateLastLogin;
use App\Models\Account;
use App\Models\ReportExport;
use App\Observers\AccountObserver;
use App\Policies\ReportPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

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

        $this->registerBankImportRateLimiter();

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if (in_array($response->statusCode(), [403, 404, 500, 503], true)) {
                return $response->render('ErrorPage', [
                    'status' => $response->statusCode(),
                ])->withSharedData();
            }
        });
    }

    /**
     * A statement import reads an uploaded file into memory, parses it, and
     * runs one long transaction, so it is the most expensive endpoint in the
     * application. Keyed by user rather than IP because the whole app is
     * authenticated and many users share one office egress address.
     */
    private function registerBankImportRateLimiter(): void
    {
        RateLimiter::for('bank-import', function (Request $request) {
            return Limit::perMinute(6)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
