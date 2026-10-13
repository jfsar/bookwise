<?php

namespace App\Providers;

use App\Contracts\FileScanner;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\ReportExport;
use App\Notifications\Channels\OrganizationDatabaseChannel;
use App\Observers\AccountObserver;
use App\Policies\AttachmentPolicy;
use App\Policies\ReportPolicy;
use App\Services\SignatureFileScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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
        $this->app->bind(FileScanner::class, SignatureFileScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ReportExport::class, ReportPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        Account::observe(AccountObserver::class);

        $this->registerNotificationChannels();

        $this->registerBankImportRateLimiter();

        $this->registerAttachmentRateLimiter();

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if (in_array($response->statusCode(), [403, 404, 500, 503], true)) {
                return $response->render('ErrorPage', [
                    'status' => $response->statusCode(),
                ])->withSharedData();
            }
        });
    }

    /**
     * The `organization-database` channel writes notifications.organization_id so
     * the notification center can enforce tenant scoping on every read and
     * mark-as-read mutation.
     */
    private function registerNotificationChannels(): void
    {
        Notification::resolved(function (ChannelManager $manager): void {
            $manager->extend('organization-database', fn (): OrganizationDatabaseChannel => new OrganizationDatabaseChannel);
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

    /**
     * File uploads are expensive and attacker-controlled, so they are rate
     * limited per authenticated user (SRS §0.27).
     */
    private function registerAttachmentRateLimiter(): void
    {
        RateLimiter::for('attachments', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
