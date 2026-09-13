<?php

namespace App\Contracts;

use App\Models\Organization;
use App\Models\User;

interface ApprovalSubject
{
    public function approvalOrganization(): Organization;

    /**
     * Transaction type used to resolve approval rules (e.g. "journal-entry").
     */
    public function approvalTransactionType(): string;

    /**
     * Monetary amount used to resolve amount-based approval rules/steps.
     */
    public function approvalAmount(): string|float;

    /**
     * Account ids referenced by the subject, used for account-scoped rules.
     *
     * @return array<int>
     */
    public function approvalAccountIds(): array;

    /**
     * Transition the subject to its approved state.
     */
    public function approveBy(User $approver, ?string $note): void;

    /**
     * Transition the subject to its rejected state with the given reason.
     */
    public function rejectBy(User $actor, string $reason): void;
}
