<?php

namespace App\Actions\User;

use App\Actions\Action;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    use Action;

    /**
     * Update a user's profile, status, and organization role.
     */
    public function handle(User $user, Organization $organization, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $organization, $data, $actor) {
            $oldValues = $user->only(['name', 'email', 'status']);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
            ]);

            if (! empty($data['role_id'])) {
                $role = Role::where('id', $data['role_id'])
                    ->where(function ($query) use ($organization) {
                        $query->where('organization_id', $organization->id)->orWhere('is_global', true);
                    })
                    ->first();

                if ($role) {
                    $user->roles()->syncWithoutDetaching([$role->id => ['organization_id' => $organization->id]]);
                }
            }

            app(AuditLogService::class)->record(
                $organization,
                $actor?->id,
                'user.updated',
                User::class,
                $user->id,
                $oldValues,
                ['name' => $data['name'], 'email' => $data['email'], 'status' => $data['status'], 'role_id' => $data['role_id'] ?? null]
            );

            return $user->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200'],
            'status' => ['required', 'in:active,inactive'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }
}
