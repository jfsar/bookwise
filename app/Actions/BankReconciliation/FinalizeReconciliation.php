<?php

namespace App\Actions\BankReconciliation;

use App\Actions\Action;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\ReconciliationMatch;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class FinalizeReconciliation
{
    use Action, MaintainsReconciliationState;

    public function handle(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor) {
            $locked = BankReconciliation::whereKey($reconciliation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isFinalized()) {
                throw new \RuntimeException('This reconciliation is already finalized.');
            }

            // §0.8 maker-checker: the reconciler may not be the preparer.
            if ($locked->prepared_by === $actor->id) {
                throw new \RuntimeException('A reconciliation must be finalized by someone other than its preparer.');
            }

            $this->recompute($locked);

            // BR-016: required differences must be resolved before finalizing.
            if (! $locked->fresh()->isBalanced()) {
                throw new \RuntimeException(
                    'The reconciliation still has an outstanding difference of '.$locked->difference.'.'
                );
            }

            $before = $this->auditValues($locked);

            $locked->update([
                'status' => 'finalized',
                'approved_by' => $actor->id,
                'reconciled_at' => now(),
                'reconciled_by' => $actor->id,
            ]);

            BankTransaction::whereIn(
                'id',
                ReconciliationMatch::where('reconciliation_id', $locked->id)->select('bank_transaction_id')
            )->update(['reconciliation_status' => 'reconciled']);

            app(AuditLogService::class)->record(
                $locked->organization,
                $actor->id,
                'bank-reconciliation.finalized',
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
            'bank_account_id', 'currency_id', 'statement_date', 'statement_start_date',
            'statement_end_date', 'opening_balance', 'closing_balance', 'reconciled_balance',
            'difference', 'status', 'prepared_by', 'approved_by', 'reconciled_at', 'reconciled_by',
        ]);
    }

    public function rules(): array
    {
        return [];
    }
}
