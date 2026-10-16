<?php

namespace App\Contracts;

/**
 * Queued jobs that act on one organization's data implement this so a failure
 * can be attributed to a tenant and surfaced to that tenant's administrators.
 */
interface OrganizationScopedJob
{
    public function organizationId(): ?int;
}
