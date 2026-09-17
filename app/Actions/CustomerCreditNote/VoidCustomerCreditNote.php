<?php

namespace App\Actions\CustomerCreditNote;

use App\Actions\Action;
use App\Enums\CustomerCreditNoteStatus;
use App\Models\CustomerCreditNote;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class VoidCustomerCreditNote
{
    use Action;

    public function handle(CustomerCreditNote $creditNote, User $voider, string $reason): CustomerCreditNote
    {
        return DB::transaction(function () use ($creditNote, $voider, $reason) {
            $creditNote = CustomerCreditNote::where('id', $creditNote->id)->lockForUpdate()->first();

            if (! $creditNote->status->isVoidable()) {
                throw new \RuntimeException('Only draft credit notes can be voided.');
            }

            $creditNote->update([
                'status' => CustomerCreditNoteStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => $reason,
                'updated_by' => $voider->id,
            ]);

            app(AuditLogService::class)->record(
                $creditNote->organization,
                $voider->id,
                'customer-credit-note.voided',
                CustomerCreditNote::class,
                $creditNote->id,
                ['status' => 'draft'],
                ['status' => 'voided', 'void_reason' => $reason]
            );

            return $creditNote->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
