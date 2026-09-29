<?php

namespace Database\Factories;

use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\ReconciliationMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationMatch>
 */
class ReconciliationMatchFactory extends Factory
{
    protected $model = ReconciliationMatch::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn (array $attributes) => BankTransaction::find($attributes['bank_transaction_id'])?->organization_id,
            'reconciliation_id' => fn (array $attributes) => BankReconciliation::factory()->create([
                'organization_id' => BankTransaction::find($attributes['bank_transaction_id'])?->organization_id,
                'bank_account_id' => BankTransaction::find($attributes['bank_transaction_id'])?->bank_account_id,
            ])->id,
            'bank_transaction_id' => BankTransaction::factory(),
            'journal_entry_line_id' => null,
            'matched_amount' => 100,
            'match_type' => 'exact',
            'created_by' => User::factory(),
        ];
    }

    public function against(BankReconciliation $reconciliation, BankTransaction $bankTransaction): static
    {
        return $this->state(fn () => [
            'organization_id' => $reconciliation->organization_id,
            'reconciliation_id' => $reconciliation->id,
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => (string) max($bankTransaction->debit, $bankTransaction->credit),
        ]);
    }
}
