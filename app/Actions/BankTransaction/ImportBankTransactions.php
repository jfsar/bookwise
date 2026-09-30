<?php

namespace App\Actions\BankTransaction;

use App\Actions\Action;
use App\Models\BankAccount;
use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportBankTransactions
{
    use Action;

    private const HEADERS = ['transaction_date', 'debit', 'credit', 'reference', 'description', 'external_transaction_id'];

    public function handle(array $data, Organization $organization, User $actor): BankImportBatch
    {
        /** @var UploadedFile $file */
        $file = $data['file'];

        return DB::transaction(function () use ($data, $organization, $actor, $file) {
            $bankAccount = BankAccount::findOrFail($data['bank_account_id']);

            if ($bankAccount->organization_id !== $organization->id) {
                throw new \RuntimeException('The bank account must belong to the same organization.');
            }

            if (! $bankAccount->isActive()) {
                throw new \RuntimeException('The bank account must be active.');
            }

            $contents = (string) $file->get();
            $checksum = hash('sha256', $contents);

            // §0.26 idempotency: the file checksum is the logical operation key.
            // Retrying a file that already imported successfully returns the
            // original batch instead of creating a new one.
            $completed = $this->completedBatch($organization, $bankAccount, $checksum);

            if ($completed) {
                return $completed;
            }

            $parsed = $this->parse($contents);
            $rowsRead = $parsed['read'];

            $batch = BankImportBatch::create([
                'organization_id' => $organization->id,
                'bank_account_id' => $bankAccount->id,
                'source' => 'csv',
                'file_name' => $file->getClientOriginalName(),
                'file_checksum' => $checksum,
                'started_at' => now(),
                'status' => 'pending',
                'rows_read' => $rowsRead,
                'created_by' => $actor->id,
            ]);

            if (! empty($parsed['errors'])) {
                $batch->update([
                    'status' => 'failed',
                    'rows_failed' => count($parsed['errors']),
                    'error_summary' => implode(PHP_EOL, array_slice($parsed['errors'], 0, 50)),
                    'completed_at' => now(),
                ]);

                app(AuditLogService::class)->record(
                    $organization,
                    $actor->id,
                    'bank-import.failed',
                    BankImportBatch::class,
                    $batch->id,
                    null,
                    $this->auditValues($batch->fresh())
                );

                return $batch->fresh();
            }

            $imported = 0;
            $skipped = 0;
            $fingerprints = [];

            foreach ($parsed['rows'] as $row) {
                $fingerprint = $this->fingerprint($row);

                if (array_key_exists($fingerprint, $fingerprints)) {
                    $skipped++;

                    continue;
                }

                try {
                    DB::transaction(function () use ($bankAccount, $batch, $row, $fingerprint) {
                        $this->createTransaction($bankAccount, $batch, $row, $fingerprint);
                    });
                    $fingerprints[$fingerprint] = true;
                    $imported++;
                } catch (QueryException $e) {
                    // PostgreSQL marks the whole transaction aborted after a
                    // constraint violation. Rolling back to the savepoint this
                    // nested transaction took lets the connection recover, so
                    // the row is treated as a duplicate and the import
                    // continues. The savepoint is mandatory, not optional.
                    if ((int) ($e->errorInfo[0] ?? 0) !== 23505) {
                        throw $e;
                    }

                    $skipped++;
                }
            }

            try {
                DB::transaction(function () use ($batch, $imported, $skipped) {
                    $batch->update([
                        'status' => 'completed',
                        'rows_imported' => $imported,
                        'rows_skipped' => $skipped,
                        'completed_at' => now(),
                    ]);
                });
            } catch (QueryException $e) {
                // Two concurrent uploads of the same file both pass the
                // completed-batch lookup above, and only one can win the
                // `uq_import_batch_completed_file` partial unique index. The
                // loser reports the winner's result instead of erroring, which
                // is what §0.26 idempotency promises the caller.
                if ((int) ($e->errorInfo[0] ?? 0) !== 23505) {
                    throw $e;
                }

                return $this->completedBatch($organization, $bankAccount, $checksum) ?? $batch->fresh();
            }

            app(AuditLogService::class)->record(
                $organization,
                $actor->id,
                'bank-import.completed',
                BankImportBatch::class,
                $batch->id,
                ['rows_read' => $rowsRead],
                $this->auditValues($batch->fresh())
            );

            return $batch->fresh();
        });
    }

    /**
     * The completed batch that already owns this file, if any. Used for the
     * idempotent short-circuit and to resolve a lost unique-index race.
     */
    private function completedBatch(Organization $organization, BankAccount $bankAccount, string $checksum): ?BankImportBatch
    {
        return BankImportBatch::forOrganization($organization)
            ->where('bank_account_id', $bankAccount->id)
            ->where('file_checksum', $checksum)
            ->where('status', 'completed')
            ->first();
    }

    /**
     * Insert one statement row.
     *
     * §0.29 auditing is deliberately done once per FILE, not once per row:
     * `AuditLogService::record()` opens its own transaction on a second
     * connection and takes a row lock on the org's audit-chain head, so
     * per-row auditing turned a 10,000-row CSV into 10,000 cross-connection
     * commits and could leave thousands of committed audit records for rows
     * that a later failure rolled back. The batch row is the audit unit for an
     * import (one file = one logical operation, §0.26) and every imported row
     * carries `import_batch_id`, so the chain and the data reconcile exactly.
     */
    private function createTransaction(BankAccount $bankAccount, BankImportBatch $batch, array $row, string $fingerprint): void
    {
        BankTransaction::create([
            'organization_id' => $bankAccount->organization_id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => $row['transaction_date'],
            'reference' => $this->nullable($row['reference'] ?? null),
            'description' => $this->nullable($row['description'] ?? null),
            'debit' => $row['debit'],
            'credit' => $row['credit'],
            'external_transaction_id' => $this->nullable($row['external_transaction_id'] ?? null),
            'import_batch_id' => $batch->id,
            'transaction_hash' => $fingerprint,
            'currency_id' => $bankAccount->currency_id,
            'source' => 'import',
            'reconciliation_status' => 'unreconciled',
        ]);
    }

    /**
     * Parse a statement CSV. The header row must contain the expected
     * columns in any order; unknown columns are ignored.
     *
     * @return array{read: int, rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private function parse(string $contents): array
    {
        $rows = [];
        $errors = [];
        $read = 0;

        $lines = $contents === '' ? [] : (preg_split('/\R/', $contents) ?: []);
        if (empty($lines)) {
            return ['read' => 0, 'rows' => [], 'errors' => ['The file is empty.']];
        }

        $firstLine = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $lines[0] ?? '') ?? '', '');
        $header = str_getcsv($firstLine);
        $header = array_map(fn (string $name) => trim(mb_strtolower($name)), $header);

        $unknown = array_diff($header, self::HEADERS);
        if (! empty($unknown)) {
            return ['read' => 0, 'rows' => [], 'errors' => ['Unexpected CSV columns: '.implode(', ', $unknown).'.']];
        }

        if (count($header) !== count(array_unique($header))) {
            return ['read' => 0, 'rows' => [], 'errors' => ['The CSV header contains duplicate columns.']];
        }

        foreach (array_slice($lines, 1) as $index => $line) {
            $lineNumber = $index + 2;
            $columns = str_getcsv($line);

            if ($columns === [null] || $columns === ['']) {
                continue;
            }

            $read++;

            if (count($columns) !== count($header)) {
                $errors[] = "Row {$lineNumber}: expected ".count($header).' columns, got '.count($columns).'.';

                continue;
            }

            $row = array_combine($header, $columns);

            try {
                $date = $this->toDate($row['transaction_date'] ?? '');
            } catch (\RuntimeException $e) {
                $errors[] = "Row {$lineNumber}: {$e->getMessage()}";

                continue;
            }

            [$debit, $credit] = $this->amounts($row);

            if ($debit === null || $credit === null) {
                $errors[] = "Row {$lineNumber}: debit and credit must be non-negative numbers.";

                continue;
            }

            if (! ($debit > 0) && ! ($credit > 0)) {
                $errors[] = "Row {$lineNumber}: each transaction must have a non-zero debit or credit.";

                continue;
            }

            $rows[] = [
                'transaction_date' => $date,
                'debit' => $debit,
                'credit' => $credit,
                'reference' => trim((string) ($row['reference'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'external_transaction_id' => trim((string) ($row['external_transaction_id'] ?? '')),
            ];
        }

        return ['read' => $read, 'rows' => $rows, 'errors' => $errors];
    }

    private function toDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \RuntimeException('transaction_date is required.');
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            throw new \RuntimeException('transaction_date must be a valid date.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: float|null, 1: float|null}
     */
    private function amounts(array $row): array
    {
        $debit = $this->toAmount($row['debit'] ?? null);
        $credit = $this->toAmount($row['credit'] ?? null);

        if ($debit === null || $credit === null) {
            return [null, null];
        }

        if ($debit > 0 && $credit > 0) {
            return [null, null];
        }

        return [$debit, $credit];
    }

    private function toAmount(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        $value = trim((string) $value);
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Deterministic fingerprint used for duplicate detection (§0.13 imported
     * transaction identity / §0.26 idempotency). Provider-specific
     * external_transaction_id takes part of the identity where available.
     *
     * @param  array<string, mixed>  $row
     */
    private function fingerprint(array $row): string
    {
        $parts = [
            $row['transaction_date'],
            number_format((float) $row['debit'], 4, '.', ''),
            number_format((float) $row['credit'], 4, '.', ''),
            $this->normalize((string) ($row['reference'] ?? '')),
            $this->normalize((string) ($row['description'] ?? '')),
            $this->normalize((string) ($row['external_transaction_id'] ?? '')),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    protected function nullable(mixed $value): ?string
    {
        return $value === '' ? null : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditValues(BankImportBatch $model): array
    {
        return $model->only([
            'bank_account_id', 'source', 'file_name', 'file_checksum',
            'status', 'rows_read', 'rows_imported', 'rows_skipped', 'rows_failed',
            'error_summary',
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'file' => ['required', 'file'],
        ];
    }
}
