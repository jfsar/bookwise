<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outbox:retry-failed
    {--limit=100 : Maximum number of re-armed events to publish in this pass}')]
#[Description('Re-arm outbox events that exhausted their attempts and relay them again')]
class RetryFailedOutboxEvents extends Command
{
    public function handle(OutboxPublisher $publisher): int
    {
        $rearmed = $publisher->retryFailed();

        $summary = $publisher->publishPending(max(1, (int) $this->option('limit')));

        $this->info(sprintf(
            'Outbox: %d event(s) re-armed, %d published, %d failed this pass, %d exhausted event(s) still awaiting retry.',
            $rearmed,
            $summary['published'],
            $summary['failed'],
            $publisher->failedCount(),
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
