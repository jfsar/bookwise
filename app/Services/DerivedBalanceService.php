<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Models\AccountDailyBalance;
use App\Models\LedgerBalanceSnapshot;
use App\Models\Organization;
use App\Models\ReportingFactTable;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the derived reporting structures from authoritative posted journal
 * data (§0.17, §0.23, INV-012).
 *
 * The structures — {@see ReportingFactTable},
 * {@see AccountDailyBalance} and
 * {@see LedgerBalanceSnapshot} — are never authoritative. This
 * service is the deterministic rebuild/reconciliation path required by
 * acceptance criterion #47: rebuilding twice from the same posted data yields
 * the same rows, and {@see verify()} reports any stored row that cannot be
 * reproduced from the journal without touching posted accounting facts.
 *
 * Both paths stream posted lines in bounded chunks and perform all monetary
 * arithmetic with exact {@see bcadd()}/{@see bcsub()} decimals, so they scale
 * to large organizations without loading the whole ledger into memory and
 * without the precision drift of floating-point arithmetic.
 */
class DerivedBalanceService
{
    private const CHUNK_SIZE = 1000;

    private const SCALE = 4;

    /** @var array<string, array{fields: list<string>, keys: list<string>, decimals: list<string>, dates: list<string>}> */
    private const TABLES = [
        'reporting_fact_tables' => [
            'fields' => [
                'organization_id',
                'journal_entry_id',
                'journal_entry_line_id',
                'account_id',
                'journal_id',
                'fiscal_year_id',
                'accounting_period_id',
                'entry_date',
                'entry_number',
                'account_code',
                'debit_amount',
                'credit_amount',
                'base_amount',
            ],
            'keys' => ['journal_entry_line_id'],
            'decimals' => ['debit_amount', 'credit_amount', 'base_amount'],
            'dates' => ['entry_date'],
        ],
        'account_daily_balances' => [
            'fields' => [
                'organization_id',
                'account_id',
                'balance_date',
                'debit_total',
                'credit_total',
                'net_change',
                'closing_balance',
            ],
            'keys' => ['account_id', 'balance_date'],
            'decimals' => ['debit_total', 'credit_total', 'net_change', 'closing_balance'],
            'dates' => ['balance_date'],
        ],
        'ledger_balance_snapshots' => [
            'fields' => [
                'organization_id',
                'account_id',
                'as_of_date',
                'debit_balance',
                'credit_balance',
                'net_balance',
            ],
            'keys' => ['account_id', 'as_of_date'],
            'decimals' => ['debit_balance', 'credit_balance', 'net_balance'],
            'dates' => ['as_of_date'],
        ],
    ];

    /**
     * Repair every derived structure for the organization from posted journal
     * data. Existing derived rows for the organization are replaced atomically;
     * posted accounting facts are never modified.
     *
     * @return array{facts: int, daily: int, snapshots: int}
     */
    public function rebuild(Organization $organization): array
    {
        return DB::transaction(function () use ($organization) {
            foreach (array_keys(self::TABLES) as $table) {
                DB::table($table)->where('organization_id', $organization->id)->delete();
            }

            $now = now();
            $movement = [];
            $facts = 0;

            foreach ($this->postedLineChunks($organization) as $lines) {
                $rows = [];

                foreach ($lines as $line) {
                    $rows[] = $this->factRow($organization, $line);
                    $this->accumulateMovement($movement, $line);
                }

                $facts += $this->insertRows('reporting_fact_tables', $rows, $now);
            }

            [$daily, $snapshots] = $this->buildBalances($organization, $movement);

            return [
                'facts' => $facts,
                'daily' => $this->insertRows('account_daily_balances', $daily, $now),
                'snapshots' => $this->insertRows('ledger_balance_snapshots', $snapshots, $now),
            ];
        });
    }

    /**
     * Compare stored derived rows against rows recomputed from posted journal
     * data. Read-only: reports drift without modifying derived or authoritative
     * data. Posted lines are streamed in bounded chunks, so memory stays
     * proportional to the number of distinct account/date pairs rather than the
     * number of posted lines.
     *
     * @return array{organization_id: int, consistent: bool, tables: array<string, array{expected: int, stored: int, differences: int, matches: bool}>}
     */
    public function verify(Organization $organization): array
    {
        $movement = [];
        $facts = $this->compareFacts($organization, $movement);
        [$daily, $snapshots] = $this->buildBalances($organization, $movement);

        $tables = [
            'reporting_fact_tables' => $facts,
            'account_daily_balances' => $this->compareExpected('account_daily_balances', self::TABLES['account_daily_balances'], $organization, $daily),
            'ledger_balance_snapshots' => $this->compareExpected('ledger_balance_snapshots', self::TABLES['ledger_balance_snapshots'], $organization, $snapshots),
        ];

        $consistent = true;

        foreach ($tables as $result) {
            $consistent = $consistent && $result['matches'];
        }

        return [
            'organization_id' => $organization->id,
            'consistent' => $consistent,
            'tables' => $tables,
        ];
    }

    /**
     * Chunk over every posted journal line for the organization, ordered by
     * line id so the result set can be paginated with keyset pagination.
     *
     * @return \Generator<int, Collection<int, object>>
     */
    private function postedLineChunks(Organization $organization): \Generator
    {
        $lastId = 0;

        while (true) {
            $lines = $this->postedLinesQuery($organization)
                ->where('lines.id', '>', $lastId)
                ->orderBy('lines.id')
                ->limit(self::CHUNK_SIZE)
                ->get();

            if ($lines->isEmpty()) {
                return;
            }

            $lastId = (int) $lines->last()->journal_entry_line_id;

            yield $lines;
        }
    }

    private function postedLinesQuery(Organization $organization): Builder
    {
        return DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'lines.journal_entry_id', '=', 'entries.id')
            ->join('accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('entries.organization_id', $organization->id)
            ->where('entries.status', JournalEntryStatus::Posted->value)
            ->select([
                'entries.id as journal_entry_id',
                'entries.journal_id',
                'entries.fiscal_year_id',
                'entries.accounting_period_id',
                'entries.entry_date',
                'entries.entry_number',
                'lines.id as journal_entry_line_id',
                'lines.account_id',
                'lines.debit',
                'lines.credit',
                'lines.base_amount',
                'accounts.code as account_code',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function factRow(Organization $organization, object $line): array
    {
        $base = $this->dec($line->base_amount);

        $isDebit = bccomp($this->dec($line->debit), '0', self::SCALE) > 0;
        $isCredit = bccomp($this->dec($line->credit), '0', self::SCALE) > 0;

        return [
            'organization_id' => $organization->id,
            'journal_entry_id' => (int) $line->journal_entry_id,
            'journal_entry_line_id' => (int) $line->journal_entry_line_id,
            'account_id' => (int) $line->account_id,
            'journal_id' => (int) $line->journal_id,
            'fiscal_year_id' => (int) $line->fiscal_year_id,
            'accounting_period_id' => (int) $line->accounting_period_id,
            'entry_date' => Carbon::parse($line->entry_date)->toDateString(),
            'entry_number' => (string) $line->entry_number,
            'account_code' => (string) $line->account_code,
            'debit_amount' => $isDebit ? $base : $this->dec(0),
            'credit_amount' => $isCredit ? $base : $this->dec(0),
            'base_amount' => $base,
        ];
    }

    /**
     * @param  array<int, array<string, array{debit: string, credit: string}>>  $movement
     */
    private function accumulateMovement(array &$movement, object $line): void
    {
        $accountId = (int) $line->account_id;
        $date = Carbon::parse($line->entry_date)->toDateString();
        $base = $this->dec($line->base_amount);

        $movement[$accountId] ??= [];
        $movement[$accountId][$date] ??= ['debit' => $this->dec(0), 'credit' => $this->dec(0)];

        if (bccomp($this->dec($line->debit), '0', self::SCALE) > 0) {
            $movement[$accountId][$date]['debit'] = bcadd($movement[$accountId][$date]['debit'], $base, self::SCALE);
        }

        if (bccomp($this->dec($line->credit), '0', self::SCALE) > 0) {
            $movement[$accountId][$date]['credit'] = bcadd($movement[$accountId][$date]['credit'], $base, self::SCALE);
        }
    }

    /**
     * @param  array<int, array<string, array{debit: string, credit: string}>>  $movement
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function buildBalances(Organization $organization, array $movement): array
    {
        ksort($movement);

        $daily = [];
        $snapshots = [];

        foreach ($movement as $accountId => $dates) {
            ksort($dates);

            $runningDebit = $this->dec(0);
            $runningCredit = $this->dec(0);
            $closing = $this->dec(0);

            foreach ($dates as $date => $totals) {
                $debitTotal = $this->dec($totals['debit']);
                $creditTotal = $this->dec($totals['credit']);
                $netChange = bcsub($debitTotal, $creditTotal, self::SCALE);

                $runningDebit = bcadd($runningDebit, $debitTotal, self::SCALE);
                $runningCredit = bcadd($runningCredit, $creditTotal, self::SCALE);
                $closing = bcadd($closing, $netChange, self::SCALE);

                $daily[] = [
                    'organization_id' => $organization->id,
                    'account_id' => $accountId,
                    'balance_date' => $date,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'net_change' => $netChange,
                    'closing_balance' => $closing,
                ];

                $snapshots[] = [
                    'organization_id' => $organization->id,
                    'account_id' => $accountId,
                    'as_of_date' => $date,
                    'debit_balance' => $runningDebit,
                    'credit_balance' => $runningCredit,
                    'net_balance' => $closing,
                ];
            }
        }

        return [$daily, $snapshots];
    }

    /**
     * @param  array<int, array<string, array{debit: string, credit: string}>>  $movement
     * @return array{expected: int, stored: int, differences: int, matches: bool}
     */
    private function compareFacts(Organization $organization, array &$movement): array
    {
        $definition = self::TABLES['reporting_fact_tables'];

        $expectedCount = (int) $this->postedLinesQuery($organization)->count();
        $storedCount = (int) DB::table('reporting_fact_tables')
            ->where('organization_id', $organization->id)
            ->count();

        $missing = 0;
        $mismatched = 0;

        foreach ($this->postedLineChunks($organization) as $lines) {
            $expected = [];

            foreach ($lines as $line) {
                $row = $this->factRow($organization, $line);
                $expected[$this->key($row, $definition['keys'])] = $this->signature($row, $definition);
                $this->accumulateMovement($movement, $line);
            }

            $stored = [];
            $storedRows = DB::table('reporting_fact_tables')
                ->where('organization_id', $organization->id)
                ->whereIn('journal_entry_line_id', $lines->pluck('journal_entry_line_id')->all())
                ->get($definition['fields']);

            foreach ($storedRows as $row) {
                $values = (array) $row;
                $stored[$this->key($values, $definition['keys'])] = $this->signature($values, $definition);
            }

            foreach ($expected as $key => $signature) {
                if (! array_key_exists($key, $stored)) {
                    $missing++;
                } elseif ($stored[$key] !== $signature) {
                    $mismatched++;
                }
            }
        }

        $extra = max(0, $storedCount - ($expectedCount - $missing));
        $differences = $missing + $mismatched + $extra;

        return [
            'expected' => $expectedCount,
            'stored' => $storedCount,
            'differences' => $differences,
            'matches' => $differences === 0,
        ];
    }

    /**
     * @param  array{fields: list<string>, keys: list<string>, decimals: list<string>, dates: list<string>}  $definition
     * @param  list<array<string, mixed>>  $expected
     * @return array{expected: int, stored: int, differences: int, matches: bool}
     */
    private function compareExpected(string $table, array $definition, Organization $organization, array $expected): array
    {
        $expectedSignatures = $this->signatures($expected, $definition);

        $storedSignatures = [];
        foreach (DB::table($table)->where('organization_id', $organization->id)->get($definition['fields']) as $row) {
            $values = (array) $row;
            $storedSignatures[$this->key($values, $definition['keys'])] = $this->signature($values, $definition);
        }

        $differences = 0;
        foreach ($expectedSignatures as $key => $signature) {
            if (($storedSignatures[$key] ?? null) !== $signature) {
                $differences++;
            }
        }

        $extra = count(array_diff_key($storedSignatures, $expectedSignatures));
        $differences += $extra;

        return [
            'expected' => count($expectedSignatures),
            'stored' => count($storedSignatures),
            'differences' => $differences,
            'matches' => $differences === 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(string $table, array $rows, Carbon $now): int
    {
        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $index => $row) {
                $chunk[$index]['created_at'] = $now;
                $chunk[$index]['updated_at'] = $now;
            }

            DB::table($table)->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{fields: list<string>, keys: list<string>, decimals: list<string>, dates: list<string>}  $definition
     * @return array<string, string>
     */
    private function signatures(array $rows, array $definition): array
    {
        $signatures = [];

        foreach ($rows as $row) {
            $signatures[$this->key($row, $definition['keys'])] = $this->signature($row, $definition);
        }

        return $signatures;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     */
    private function key(array $values, array $keys): string
    {
        return implode('|', array_map(fn (string $key) => (string) ($values[$key] ?? ''), $keys));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array{fields: list<string>, keys: list<string>, decimals: list<string>, dates: list<string>}  $definition
     */
    private function signature(array $values, array $definition): string
    {
        $parts = [];

        foreach ($definition['fields'] as $field) {
            $value = $values[$field] ?? null;

            $parts[] = match (true) {
                in_array($field, $definition['decimals'], true) => $this->dec($value),
                in_array($field, $definition['dates'], true) => Carbon::parse($value)->toDateString(),
                default => (string) ($value ?? ''),
            };
        }

        return implode('|', $parts);
    }

    /**
     * Normalize any monetary value to an exact four-decimal string.
     */
    private function dec(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', self::SCALE);
    }
}
