<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if ($organization) {
            $this->authorize('viewAny', [AuditLog::class, $organization]);
        }

        $logs = $organization
            ? AuditLog::where('organization_id', $organization->id)
                ->with('user')
                ->when($request->filled('search'), fn (Builder $query) => $query->where(function (Builder $query) use ($request) {
                    $term = '%'.$request->string('search').'%';

                    $query->where('action', 'ilike', $term)->orWhere('auditable_type', 'ilike', $term);
                }))
                ->when($request->filled('user_id'), fn (Builder $query) => $query->where('audit_logs.user_id', $request->integer('user_id')))
                ->when($request->filled('date_from'), fn (Builder $query) => $query->where('created_at', '>=', $request->string('date_from').' 00:00:00'))
                ->when($request->filled('date_to'), fn (Builder $query) => $query->where('created_at', '<=', $request->string('date_to').' 23:59:59'))
                ->orderByDesc('sequence_no')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (AuditLog $log) => $this->serialize($log))
                ->toArray()
            : null;

        $members = $organization
            ? $organization->users()
                ->orderBy('name')
                ->get()
                ->values()
                ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name])
            : [];

        return Inertia::render('Settings/AuditLogs', [
            'logs' => $logs,
            'members' => $members,
            'filters' => $request->only(['search', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'sequence_no' => $log->sequence_no,
            'action' => $log->action,
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'ip_address' => $log->ip_address,
            'auditable' => class_basename($log->auditable_type).($log->auditable_id ? " #{$log->auditable_id}" : ''),
            'created_at' => $log->created_at?->toDateTimeString(),
            'has_hash' => ! is_null($log->record_hash),
        ];
    }
}
