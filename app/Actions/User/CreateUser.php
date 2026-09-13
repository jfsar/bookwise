<?php

namespace App\Actions\User;

use App\Actions\Action;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    use Action;

    /**
     * Create a user and add them to the given organization with a role.
     */
    public function handle(array $data, Organization $organization, User $creator): User
    {
        return DB::transaction(function () use ($data, $organization, $creator) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                    'email_verified_at' => $data['email_verified_at'] ?? now(),
                    'status' => 'active',
                ]
            );

            $user->organizations()->syncWithoutDetaching($organization->id, [
                'membership_status' => MembershipStatus::Active,
                'joined_at' => now(),
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
                $creator->id,
                'user.created',
                User::class,
                $user->id,
                null,
                ['email' => $user->email, 'role_id' => $data['role_id'] ?? null]
            );

            return $user->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }
}
