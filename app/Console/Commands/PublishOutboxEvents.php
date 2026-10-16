<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outbox:publish
    {--limit=100 : Maximum number of pending events to publish in one pass}')]
#[Description('Publish due outbox events (crash-recovery relay for the transactional outbox)')]
class PublishOutboxEvents extends Command
{
    public function handle(OutboxPublisher $publisher): int
    {
        $summary = $publisher->publishPending(max(1, (int) $this->option('limit')));

        $this->info(sprintf(
            'Outbox: %d published, %d failed this pass, %d exhausted event(s) awaiting manual retry.',
            $summary['published'],
            $summary['failed'],
            $publisher->failedCount(),
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
