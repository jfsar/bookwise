<?php

namespace App\Policies;

use App\Models\CustomerInvoice;
use App\Models\Organization;
use App\Models\User;

class CustomerInvoicePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('view-invoices', $organization);
    }

    public function view(User $user, CustomerInvoice $invoice): bool
    {
        return $user->hasPermissionTo('view-invoices', $invoice->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('create-invoices', $organization);
    }

    public function post(User $user, CustomerInvoice $invoice): bool
    {
        return $user->hasPermissionTo('approve-invoices', $invoice->organization);
    }

    public function void(User $user, CustomerInvoice $invoice): bool
    {
        return $user->hasPermissionTo('void-invoices', $invoice->organization);
    }
}
