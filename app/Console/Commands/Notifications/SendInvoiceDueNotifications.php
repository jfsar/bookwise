<?php

namespace App\Console\Commands\Notifications;

use App\Enums\CustomerInvoiceStatus;
use App\Models\CustomerInvoice;
use App\Notifications\InvoiceDueSoon;
use App\Notifications\InvoiceOverdue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-invoice-due')]
#[Description('Send due/overdue invoice notifications')]
class SendInvoiceDueNotifications extends Command
{
    public function handle(): int
    {
        $today = now()->toDateString();
        $soon = now()->addDays(7)->toDateString();

        CustomerInvoice::with(['customer.user', 'organization'])
            ->whereNotNull('due_date')
            ->whereIn('status', [CustomerInvoiceStatus::Open, CustomerInvoiceStatus::PartiallyPaid])
            ->get()
            ->each(function ($invoice) use ($today, $soon) {
                if ($invoice->due_date->toDateString() < $today && $invoice->customer?->user) {
                    $invoice->customer->user->notify(new InvoiceOverdue($invoice->ulid ?? (string) $invoice->id, $invoice->invoice_number, $invoice->due_date->toDateString()));
                } elseif ($invoice->due_date->toDateString() <= $soon && $invoice->due_date->toDateString() >= $today && $invoice->customer?->user) {
                    $invoice->customer->user->notify(new InvoiceDueSoon($invoice->ulid ?? (string) $invoice->id, $invoice->invoice_number, $invoice->due_date->toDateString()));
                }
            });

        return Command::SUCCESS;
    }
}
