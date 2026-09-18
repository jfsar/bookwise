<?php

namespace App\Actions\VendorPayment;

use App\Actions\Action;
use App\Actions\JournalEntry\ReverseJournalEntry;
use App\Enums\VendorPaymentStatus;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class VoidVendorPayment
{
    use Action;

    public function handle(VendorPayment $payment, User $voider, string $reason): VendorPayment
    {
        return DB::transaction(function () use ($payment, $voider, $reason) {
            $payment = VendorPayment::where('id', $payment->id)->lockForUpdate()->first();

            if (! $payment->status->isVoidable()) {
                throw new \RuntimeException('Only draft or posted payments can be voided.');
            }

            $wasPosted = $payment->status === VendorPaymentStatus::Posted;
            $previous = ['status' => $payment->status->value];

            if ($wasPosted) {
                $entry = $payment->journalEntry;

                if (! $entry || ! $entry->isPosted()) {
                    throw new \RuntimeException('A posted journal entry is required to void a posted payment.');
                }

                (new ReverseJournalEntry)->handle($entry, $voider, $reason);
            }

            $payment->update([
                'status' => VendorPaymentStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => $reason,
            ]);

            app(AuditLogService::class)->record(
                $payment->organization,
                $voider->id,
                'vendor-payment.voided',
                VendorPayment::class,
                $payment->id,
                $previous,
                ['status' => 'voided', 'void_reason' => $reason]
            );

            return $payment->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
