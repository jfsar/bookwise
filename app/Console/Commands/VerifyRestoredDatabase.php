<?php

namespace App\Console\Commands;

use App\Services\DataIntegrityVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:verify-restore')]
#[Description('Verify a restored database: schema current, audit chains intact, journal balances reconcile')]
class VerifyRestoredDatabase extends Command
{
    public function handle(DataIntegrityVerifier $verifier): int
    {
        $report = $verifier->verify();

        foreach ($report->checks as $name => $check) {
            $this->line(sprintf(
                '%s %s — %s',
                $check['passed'] ? '<info>[PASS]</info>' : '<error>[FAIL]</error>',
                $name,
                $check['detail'],
            ));
        }

        if (! $report->passed()) {
            $this->error('Restore verification failed.');

            return self::FAILURE;
        }

        $this->info('Restore verification passed.');

        return self::SUCCESS;
    }
}
