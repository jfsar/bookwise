<?php

namespace App\Listeners;

use App\Dashboard\DashboardMetricsService;
use App\Events\JournalPosted;

class InvalidateDashboardCache
{
    public function handle(JournalPosted $event): void
    {
        DashboardMetricsService::forgetForOrganization($event->entry->organization_id);
    }
}
