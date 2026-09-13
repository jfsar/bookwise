<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    /**
     * Record an audit log entry with a tamper-evident hash chain per organization.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        Organization $organization,
        ?int $userId,
        string $action,
        string $auditableType,
        ?int $auditableId,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return DB::transaction(function () use ($organization, $userId, $action, $auditableType, $auditableId, $oldValues, $newValues) {
            $previous = AuditLog::where('organization_id', $organization->id)
                ->orderByDesc('sequence_no')
                ->lockForUpdate()
                ->first();

            $sequenceNo = ($previous?->sequence_no ?? 0) + 1;
            $previousHash = $previous?->record_hash ?? str_repeat('0', 64);
            $createdAt = now();

            $payload = $this->payload(
                $organization->id,
                $sequenceNo,
                $action,
                $auditableType,
                $auditableId,
                $oldValues,
                $newValues,
                $createdAt->toIso8601String(),
            );

            $recordHash = hash('sha256', $previousHash.$payload);

            $log = new AuditLog;
            $log->fill([
                'organization_id' => $organization->id,
                'user_id' => $userId,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-Id'),
                'sequence_no' => $sequenceNo,
                'previous_hash' => $previousHash,
                'record_hash' => $recordHash,
                'retention_class' => 'retain',
            ]);
            $log->created_at = $createdAt;
            $log->save();

            return $log;
        });
    }

    /**
     * Recompute the audit-log hash chain for an organization and report every
     * record whose stored hash evidence does not reproduce, providing tamper
     * evidence on top of the append-only chain written by record().
     */
    public function verify(Organization $organization): AuditLogVerificationResult
    {
        $failures = [];
        $checkedCount = 0;
        $expectedSequence = 1;
        $expectedPreviousHash = str_repeat('0', 64);

        foreach (AuditLog::query()
            ->where('organization_id', $organization->id)
            ->orderBy('sequence_no')
            ->cursor() as $log) {
            $checkedCount++;
            $sequenceNo = (int) $log->sequence_no;

            if ($sequenceNo !== $expectedSequence) {
                $failures[] = [
                    'sequence_no' => $sequenceNo,
                    'reason' => 'Sequence number is not contiguous (record missing, inserted, or reordered).',
                ];
            }

            if ($log->previous_hash !== $expectedPreviousHash) {
                $failures[] = [
                    'sequence_no' => $sequenceNo,
                    'reason' => 'previous_hash does not link to the preceding record hash.',
                ];
            }

            $createdAt = $log->created_at?->toIso8601String();
            if ($createdAt === null) {
                $failures[] = [
                    'sequence_no' => $sequenceNo,
                    'reason' => 'created_at is missing so the record hash cannot be recomputed.',
                ];
            } else {
                $payload = $this->payload(
                    (int) $log->organization_id,
                    $sequenceNo,
                    $log->action,
                    $log->auditable_type,
                    $log->auditable_id,
                    $log->old_values,
                    $log->new_values,
                    $createdAt,
                );

                $recomputedHash = hash('sha256', $expectedPreviousHash.$payload);
                if ($recomputedHash !== $log->record_hash) {
                    $failures[] = [
                        'sequence_no' => $sequenceNo,
                        'reason' => 'record_hash does not match the recomputed chain hash (record tampered).',
                    ];
                }
            }

            $expectedSequence++;
            $expectedPreviousHash = $log->record_hash;
        }

        return new AuditLogVerificationResult(count($failures) === 0, $checkedCount, $failures);
    }

    /**
     * Serialize the exact payload that defines a record's chain hash.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function payload(
        int $organizationId,
        int $sequenceNo,
        string $action,
        string $auditableType,
        ?int $auditableId,
        ?array $oldValues,
        ?array $newValues,
        string $createdAt,
    ): string {
        return json_encode([
            'organization_id' => $organizationId,
            'sequence_no' => $sequenceNo,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => $createdAt,
        ], JSON_THROW_ON_ERROR);
    }
}
