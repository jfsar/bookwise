<?php

namespace App\Actions\BankReconciliation;

use App\Actions\Action;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\ReconciliationMatch;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Controlled correction path. A finalized reconciliation is immutable
 * (§0.13 / §7.34); reopening it is the only way to change matched data and is
 * separately audited (§0.29 "reconciliation finalization/reopen").
 */
class ReopenReconciliation
{
    use Action;

    public function handle(BankReconciliation $reconciliation, array $data, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $data, $actor) {
            $locked = BankReconciliation::whereKey($reconciliation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isFinalized()) {
                throw new \RuntimeException('Only a finalized reconciliation can be reopened.');
            }

            $before = $this->auditValues($locked);

            $locked->update([
                'status' => 'reopened',
                'approved_by' => null,
                'reconciled_at' => null,
                'reconciled_by' => null,
                'finalization_reason' => $data['reason'],
            ]);

            $bankTransactionIds = ReconciliationMatch::where('reconciliation_id', $locked->id)
                ->pluck('bank_transaction_id')
                ->all();

            BankTransaction::whereIn('id', $bankTransactionIds)
                ->update(['reconciliation_status' => 'matched']);

            app(AuditLogService::class)->record(
                $locked->organization,
                $actor->id,
                'bank-reconciliation.reopened',
                BankReconciliation::class,
                $locked->id,
                $before,
                $this->auditValues($locked)
            );

            return $locked->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditValues(BankReconciliation $reconciliation): array
    {
        return $reconciliation->only([
            'bank_account_id', 'currency_id', 'statement_start_date', 'statement_end_date',
            'reconciled_balance', 'difference', 'status', 'prepared_by', 'approved_by',
            'reconciled_at', 'reconciled_by', 'finalization_reason',
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
