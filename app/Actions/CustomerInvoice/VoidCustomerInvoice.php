<?php

namespace App\Actions\CustomerInvoice;

use App\Actions\Action;
use App\Enums\CustomerInvoiceStatus;
use App\Models\CustomerInvoice;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class VoidCustomerInvoice
{
    use Action;

    public function handle(CustomerInvoice $invoice, User $voider, string $reason): CustomerInvoice
    {
        return DB::transaction(function () use ($invoice, $voider, $reason) {
            $invoice = CustomerInvoice::where('id', $invoice->id)->lockForUpdate()->first();

            if (! $invoice->status->isVoidable()) {
                throw new \RuntimeException('Only draft invoices can be voided.');
            }

            $invoice->update([
                'status' => CustomerInvoiceStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => $reason,
                'updated_by' => $voider->id,
            ]);

            app(AuditLogService::class)->record(
                $invoice->organization,
                $voider->id,
                'customer-invoice.voided',
                CustomerInvoice::class,
                $invoice->id,
                ['status' => 'draft'],
                ['status' => 'voided', 'void_reason' => $reason]
            );

            return $invoice->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
