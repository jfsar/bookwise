<?php

namespace App\Actions\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\ReconciliationMatch;

/**
 * Shared state derivation for the reconciliation lifecycle.
 *
 * reconciled_balance and difference are DERIVED values (§0.17 / AP-005): they
 * are recomputed from the bank transactions covered by this reconciliation's
 * matches and can always be rebuilt from authoritative data.
 */
trait MaintainsReconciliationState
{
    protected function recompute(BankReconciliation $reconciliation): BankReconciliation
    {
        // The balance movement comes from the MATCHED portion of each bank
        // transaction, not its face amount: a partially matched transaction
        // only moves the statement by the part that was reconciled.
        $totals = ReconciliationMatch::query()
            ->join('bank_transactions', 'bank_transactions.id', '=', 'reconciliation_matches.bank_transaction_id')
            ->where('reconciliation_matches.reconciliation_id', $reconciliation->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN bank_transactions.credit > 0 THEN reconciliation_matches.matched_amount ELSE 0 END), 0) AS credits')
            ->selectRaw('COALESCE(SUM(CASE WHEN bank_transactions.debit > 0 THEN reconciliation_matches.matched_amount ELSE 0 END), 0) AS debits')
            ->first();

        $reconciled = bcadd(
            bcsub((string) $reconciliation->opening_balance, (string) $totals->debits, 4),
            (string) $totals->credits,
            4
        );

        $reconciliation->update([
            'reconciled_balance' => $reconciled,
            'difference' => bcsub((string) $reconciliation->closing_balance, $reconciled, 4),
        ]);

        return $reconciliation;
    }

    /**
     * A bank transaction is "matched" while at least one match survives and
     * "reconciled" only once a finalized reconciliation owns it (§7.34).
     */
    protected function refreshTransactionStatus(int $bankTransactionId): void
    {
        $bankTransaction = BankTransaction::whereKey($bankTransactionId)->lockForUpdate()->first();

        if (! $bankTransaction) {
            return;
        }

        $hasMatch = ReconciliationMatch::where('bank_transaction_id', $bankTransactionId)->exists();

        if ($hasMatch) {
            $status = $bankTransaction->reconciliation_status === 'reconciled' ? 'reconciled' : 'matched';
        } else {
            $status = 'unreconciled';
        }

        if ($status !== $bankTransaction->reconciliation_status) {
            $bankTransaction->update(['reconciliation_status' => $status]);
        }
    }
}
