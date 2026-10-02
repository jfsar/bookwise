<?php

namespace App\Console\Commands\Notifications;

use App\Enums\VendorBillStatus;
use App\Models\VendorBill;
use App\Notifications\VendorBillDue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-vendor-bill-due')]
#[Description('Send due vendor bill notifications')]
class SendVendorBillDueNotifications extends Command
{
    public function handle(): int
    {
        $today = now()->toDateString();
        $soon = now()->addDays(7)->toDateString();

        VendorBill::with(['vendor.user', 'organization'])
            ->whereNotNull('due_date')
            ->whereIn('status', [VendorBillStatus::Open, VendorBillStatus::PartiallyPaid])
            ->get()
            ->each(function ($bill) use ($soon) {
                if ($bill->due_date->toDateString() <= $soon && $bill->vendor?->user) {
                    $bill->vendor->user->notify(new VendorBillDue($bill->ulid ?? (string) $bill->id, $bill->bill_number, $bill->due_date->toDateString()));
                }
            });

        return Command::SUCCESS;
    }
}
