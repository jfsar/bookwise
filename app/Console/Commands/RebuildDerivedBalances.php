<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\DerivedBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('accounting:rebuild-derived-balances
    {--organization= : Restrict to a single organization id}
    {--check : Verify derived structures against posted journal data without writing}')]
#[Description('Rebuild or verify derived reporting balances from posted journal data')]
class RebuildDerivedBalances extends Command
{
    public function handle(DerivedBalanceService $derivedBalances): int
    {
        $organizations = $this->organizations();

        if ($organizations === null) {
            return self::FAILURE;
        }

        if ($organizations->isEmpty()) {
            $this->warn('No organizations found.');

            return self::SUCCESS;
        }

        $check = (bool) $this->option('check');
        $inconsistent = false;

        foreach ($organizations as $organization) {
            if ($check) {
                $result = $derivedBalances->verify($organization);

                $this->line(sprintf(
                    'Org %d: facts %d, daily %d, snapshots %d%s',
                    $organization->id,
                    $result['tables']['reporting_fact_tables']['expected'],
                    $result['tables']['account_daily_balances']['expected'],
                    $result['tables']['ledger_balance_snapshots']['expected'],
                    $result['consistent'] ? ' — consistent' : ' — DRIFT DETECTED',
                ));

                if (! $result['consistent']) {
                    $inconsistent = true;

                    foreach ($result['tables'] as $table => $stats) {
                        if (! $stats['matches']) {
                            $this->warn(sprintf('  %s: %d difference(s).', $table, $stats['differences']));
                        }
                    }
                }

                continue;
            }

            $counts = $derivedBalances->rebuild($organization);

            $this->info(sprintf(
                'Org %d rebuilt: %d fact(s), %d daily balance(s), %d snapshot(s).',
                $organization->id,
                $counts['facts'],
                $counts['daily'],
                $counts['snapshots'],
            ));
        }

        return $inconsistent ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Organization>|null
     */
    private function organizations()
    {
        $id = $this->option('organization');

        if ($id === null) {
            return Organization::orderBy('id')->get();
        }

        $organization = Organization::find($id);

        if (! $organization) {
            $this->error("Organization [{$id}] not found.");

            return null;
        }

        return collect([$organization]);
    }
}
