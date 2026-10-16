<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verifies that a restored database is usable (§0.31): the schema is current,
 * every organization's audit hash chain still reproduces, and posted journals
 * reconcile. A backup that has never been restored and verified is not a
 * verified backup, so this runs against a freshly restored database before it
 * is trusted — locally, in CI, or during a disaster recovery drill.
 */
class DataIntegrityVerifier
{
    public function __construct(
        private AuditLogService $auditLogs,
        private Migrator $migrator,
    ) {}

    public function verify(): DataIntegrityReport
    {
        $checks = [];

        $this->run($checks, 'migrations', fn (): array => $this->checkMigrations());
        $this->run($checks, 'audit_chain', fn (): array => $this->checkAuditChains());
        $this->run($checks, 'posted_entries_balanced', fn (): array => $this->checkPostedEntriesBalanced());
        $this->run($checks, 'trial_balance', fn (): array => $this->checkTrialBalance());

        return new DataIntegrityReport($checks);
    }

    /**
     * @param  array<string, array{passed: bool, detail: string}>  $checks
     * @param  callable(): array{passed: bool, detail: string}  $check
     */
    private function run(array &$checks, string $name, callable $check): void
    {
        try {
            $checks[$name] = $check();
        } catch (Throwable $e) {
            $checks[$name] = [
                'passed' => false,
                'detail' => 'Check could not run: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function checkMigrations(): array
    {
        $ran = $this->migrator->getRepository()->getRan();
        $files = array_keys($this->migrator->getMigrationFiles([database_path('migrations')]));
        $pending = array_values(array_diff($files, $ran));

        if ($pending !== []) {
            return [
                'passed' => false,
                'detail' => sprintf(
                    '%d pending migration(s): the restored schema is behind the code (%s).',
                    count($pending),
                    implode(', ', array_slice($pending, 0, 5)),
                ),
            ];
        }

        return [
            'passed' => true,
            'detail' => sprintf('%d migration(s) applied; schema is current.', count($ran)),
        ];
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function checkAuditChains(): array
    {
        $checked = 0;
        $invalid = [];

        foreach (Organization::query()->orderBy('id')->cursor() as $organization) {
            $checked++;

            if (! $this->auditLogs->verify($organization)->valid) {
                $invalid[] = $organization->id;
            }
        }

        if ($invalid !== []) {
            return [
                'passed' => false,
                'detail' => sprintf(
                    'Tamper evidence in audit chain(s) for organization(s): %s.',
                    implode(', ', $invalid),
                ),
            ];
        }

        return [
            'passed' => true,
            'detail' => sprintf('%d organization audit chain(s) verified.', $checked),
        ];
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function checkPostedEntriesBalanced(): array
    {
        $unbalanced = DB::table('journal_entries')
            ->where('status', JournalEntryStatus::Posted->value)
            ->whereRaw('total_debit <> total_credit')
            ->count();

        if ($unbalanced > 0) {
            return [
                'passed' => false,
                'detail' => sprintf('%d posted journal entry(ies) have total_debit <> total_credit.', $unbalanced),
            ];
        }

        return ['passed' => true, 'detail' => 'All posted journal entries balance.'];
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function checkTrialBalance(): array
    {
        $unbalancedOrganizations = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->where('entries.status', JournalEntryStatus::Posted->value)
            ->groupBy('entries.organization_id')
            ->havingRaw('COALESCE(SUM(lines.debit), 0) <> COALESCE(SUM(lines.credit), 0)')
            ->pluck('entries.organization_id')
            ->all();

        if ($unbalancedOrganizations !== []) {
            return [
                'passed' => false,
                'detail' => sprintf(
                    'Trial balance does not reconcile for organization(s): %s.',
                    implode(', ', $unbalancedOrganizations),
                ),
            ];
        }

        return ['passed' => true, 'detail' => 'Posted journal lines reconcile (debits = credits).'];
    }
}
