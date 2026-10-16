<?php

namespace App\Console\Commands;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('idempotency:expire-stale
    {--prune-days=0 : Delete completed, failed, and expired keys older than this many days (0 disables pruning)}')]
#[Description('Mark abandoned idempotency reservations as expired and optionally prune old keys')]
class ExpireStaleIdempotencyKeys extends Command
{
    public function handle(): int
    {
        $expired = IdempotencyKey::query()
            ->where('status', IdempotencyStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => IdempotencyStatus::Expired->value,
                'updated_at' => now(),
            ]);

        $this->info("Idempotency: {$expired} stale reservation(s) marked expired.");

        $pruneDays = max(0, (int) $this->option('prune-days'));

        if ($pruneDays > 0) {
            $cutoff = now()->subDays($pruneDays);

            $pruned = IdempotencyKey::query()
                ->whereIn('status', [
                    IdempotencyStatus::Completed->value,
                    IdempotencyStatus::Failed->value,
                    IdempotencyStatus::Expired->value,
                ])
                ->where('updated_at', '<', $cutoff)
                ->delete();

            $this->info("Idempotency: {$pruned} key(s) older than {$pruneDays} day(s) pruned.");
        }

        return self::SUCCESS;
    }
}
