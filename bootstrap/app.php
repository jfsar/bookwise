<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveClientIp;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ResolveClientIp::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->withSchedule(function (Schedule $schedule): void {
        $schedule->command('recurring-journals:generate')
            ->dailyAt('01:00')
            ->withoutOverlapping();

        $schedule->command('notifications:send-invoice-due')
            ->dailyAt('06:00')
            ->withoutOverlapping();

        $schedule->command('notifications:send-vendor-bill-due')
            ->dailyAt('06:15')
            ->withoutOverlapping();

        $schedule->command('notifications:send-period-closing-reminders')
            ->dailyAt('06:30')
            ->withoutOverlapping();

        $schedule->command('accounting:rebuild-derived-balances')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        $schedule->command('outbox:publish')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('queue:prune-failed --hours=720')
            ->dailyAt('03:30')
            ->withoutOverlapping();

        $schedule->command('idempotency:expire-stale --prune-days=30')
            ->dailyAt('04:00')
            ->withoutOverlapping();
    })->create();
