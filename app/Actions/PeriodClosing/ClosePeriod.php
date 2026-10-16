<?php

namespace App\Actions\PeriodClosing;

use App\Actions\Action;
use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\OperationalMetrics;
use Illuminate\Support\Facades\DB;

class ClosePeriod
{
    use Action;

    public function handle(AccountingPeriod $period, User $closer, ?string $reason = null): AccountingPeriod
    {
        return app(OperationalMetrics::class)->measure(
            'period_close_duration',
            fn (): AccountingPeriod => DB::transaction(function () use ($period, $closer, $reason) {
                if (! $period->isOpen()) {
                    throw new \RuntimeException('Only open periods can be closed.');
                }

                $unpostedEntries = JournalEntry::where('accounting_period_id', $period->id)
                    ->whereIn('status', ['draft', 'submitted'])
                    ->count();

                if ($unpostedEntries > 0) {
                    throw new \RuntimeException("Cannot close period with {$unpostedEntries} unposted entries. Submit, approve, or cancel all entries first.");
                }

                $period->update([
                    'status' => AccountingPeriodStatus::Closed,
                    'closed_by' => $closer->id,
                    'closed_at' => now(),
                    'closed_reason' => $reason,
                ]);

                return $period->fresh();
            }),
            ['organization_id' => $period->organization_id],
        );
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
