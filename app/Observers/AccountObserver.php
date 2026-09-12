<?php

namespace App\Observers;

use App\Dashboard\DashboardMetricsService;
use App\Models\Account;

class AccountObserver
{
    public function saved(Account $account): void
    {
        $this->forgetDashboard($account);
    }

    public function deleted(Account $account): void
    {
        $this->forgetDashboard($account);
    }

    protected function forgetDashboard(Account $account): void
    {
        if ($account->organization_id !== null) {
            DashboardMetricsService::forgetForOrganization($account->organization_id);
        }
    }
}
