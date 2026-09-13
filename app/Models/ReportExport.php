<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $user_id
 * @property-read ReportType $report_type
 * @property-read array<string, mixed> $filters
 * @property-read string $request_hash
 * @property-read ReportExportStatus $status
 * @property-read string|null $file_name
 * @property-read string|null $file_path
 * @property-read int|null $file_size
 * @property-read string|null $error_message
 * @property-read Carbon|null $started_at
 * @property-read Carbon|null $completed_at
 */
class ReportExport extends Model
{
    /** @use Notifiable<ReportExport> */
    use Notifiable;

    protected $fillable = [
        'organization_id',
        'user_id',
        'report_type',
        'filters',
        'request_hash',
        'status',
        'file_name',
        'file_path',
        'file_size',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'report_type' => ReportType::class,
            'status' => ReportExportStatus::class,
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routeNotificationForMail(): string
    {
        return $this->user()->value('email');
    }

    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }
}
