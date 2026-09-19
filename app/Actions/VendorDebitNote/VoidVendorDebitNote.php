<?php

namespace App\Actions\VendorDebitNote;

use App\Actions\Action;
use App\Enums\VendorDebitNoteStatus;
use App\Models\User;
use App\Models\VendorDebitNote;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class VoidVendorDebitNote
{
    use Action;

    public function handle(VendorDebitNote $debitNote, User $voider, string $reason): VendorDebitNote
    {
        return DB::transaction(function () use ($debitNote, $voider, $reason) {
            $debitNote = VendorDebitNote::where('id', $debitNote->id)->lockForUpdate()->first();

            if (! $debitNote->status->isVoidable()) {
                throw new \RuntimeException('Only draft debit notes can be voided.');
            }

            $debitNote->update([
                'status' => VendorDebitNoteStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => $reason,
            ]);

            app(AuditLogService::class)->record(
                $debitNote->organization,
                $voider->id,
                'vendor-debit-note.voided',
                VendorDebitNote::class,
                $debitNote->id,
                ['status' => 'draft'],
                ['status' => 'voided', 'void_reason' => $reason]
            );

            return $debitNote->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
