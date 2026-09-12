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

            $payload = json_encode([
                'organization_id' => $organization->id,
                'sequence_no' => $sequenceNo,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'created_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR);

            $recordHash = hash('sha256', $previousHash.$payload);

            return AuditLog::create([
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
        });
    }
}
