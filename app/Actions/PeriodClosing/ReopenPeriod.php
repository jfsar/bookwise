<?php

namespace App\Actions\PeriodClosing;

use App\Actions\Action;
use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReopenPeriod
{
    use Action;

    public function handle(AccountingPeriod $period, User $reopener, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $reopener, $reason) {
            if ($period->status !== AccountingPeriodStatus::Closed) {
                throw new \RuntimeException('Only closed periods can be reopened.');
            }

            $nextPeriod = AccountingPeriod::where('organization_id', $period->organization_id)
                ->where('fiscal_year_id', $period->fiscal_year_id)
                ->where('period_number', $period->period_number + 1)
                ->first();

            if ($nextPeriod && ! $nextPeriod->isOpen()) {
                throw new \RuntimeException('Cannot reopen this period because the next period is already closed.');
            }

            $period->update([
                'status' => AccountingPeriodStatus::Open,
                'closed_by' => null,
                'closed_at' => null,
                'closed_reason' => null,
                'reopened_by' => $reopener->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ]);

            return $period->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
