<?php

namespace App\Actions\Role;

use App\Actions\Action;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRole
{
    use Action;

    public function handle(array $data, Organization $organization, ?User $actor = null): Role
    {
        return DB::transaction(function () use ($data, $organization, $actor) {
            $role = Role::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name'], $organization),
                'description' => $data['description'] ?? null,
                'is_global' => false,
            ]);

            $role->permissions()->sync($data['permission_ids'] ?? []);

            app(AuditLogService::class)->record(
                $organization,
                $actor?->id,
                'role.created',
                Role::class,
                $role->id,
                null,
                ['name' => $role->name, 'permission_ids' => $data['permission_ids'] ?? []]
            );

            return $role;
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

    private function uniqueSlug(string $name, Organization $organization): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Role::where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
