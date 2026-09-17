<?php

namespace App\Actions\CustomerPayment;

use App\Actions\Action;
use App\Actions\JournalEntry\ReverseJournalEntry;
use App\Enums\CustomerPaymentStatus;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CustomerInvoiceSettlement;
use Illuminate\Support\Facades\DB;

class VoidCustomerPayment
{
    use Action;

    public function handle(CustomerPayment $payment, User $voider, string $reason): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $voider, $reason) {
            $payment = CustomerPayment::where('id', $payment->id)->lockForUpdate()->first();

            if (! $payment->status->isVoidable()) {
                throw new \RuntimeException('Only draft or posted payments can be voided.');
            }

            $wasPosted = $payment->status === CustomerPaymentStatus::Posted;
            $previous = ['status' => $payment->status->value];

            if ($wasPosted) {
                $entry = $payment->journalEntry;

                if (! $entry || ! $entry->isPosted()) {
                    throw new \RuntimeException('A posted journal entry is required to void a posted payment.');
                }

                (new ReverseJournalEntry)->handle($entry, $voider, $reason);
            }

            $payment->update([
                'status' => CustomerPaymentStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => $reason,
            ]);

            if ($wasPosted) {
                app(CustomerInvoiceSettlement::class)->reconcileForPayment($payment, $voider);
            }

            app(AuditLogService::class)->record(
                $payment->organization,
                $voider->id,
                'customer-payment.voided',
                CustomerPayment::class,
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
