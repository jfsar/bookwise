<?php

namespace App\Actions\Role;

use App\Actions\Action;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class DeleteRole
{
    use Action;

    public function handle(Role $role, Organization $organization, ?User $actor = null): void
    {
        if ($role->is_global) {
            throw new \RuntimeException('System roles cannot be deleted.');
        }

        DB::transaction(function () use ($role, $organization, $actor) {
            app(AuditLogService::class)->record(
                $organization,
                $actor?->id,
                'role.deleted',
                Role::class,
                $role->id,
                ['name' => $role->name],
                null
            );

            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        });
    }

    public function rules(): array
    {
        return [];
    }
}
