<?php

namespace App\Actions\Role;

use App\Actions\Action;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateRole
{
    use Action;

    public function handle(Role $role, array $data, ?User $actor = null): Role
    {
        return DB::transaction(function () use ($role, $data, $actor) {
            if ($role->is_global) {
                throw new \RuntimeException('System roles cannot be edited.');
            }

            $oldValues = $role->only(['name', 'description']);

            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $role->permissions()->sync($data['permission_ids'] ?? []);

            app(AuditLogService::class)->record(
                $role->organization,
                $actor?->id,
                'role.updated',
                Role::class,
                $role->id,
                $oldValues,
                ['name' => $role->name, 'description' => $role->description, 'permission_ids' => $data['permission_ids'] ?? []]
            );

            return $role->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
