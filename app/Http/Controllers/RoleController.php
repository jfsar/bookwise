<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\Role\CreateRole;
use App\Actions\Role\DeleteRole;
use App\Actions\Role\UpdateRole;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        $roles = $organization
            ? Role::where(function ($query) use ($organization) {
                $query->where('organization_id', $organization->id)->orWhere('is_global', true);
            })
                ->with('permissions')
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Settings/Roles', [
            'roles' => $roles->values()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_global' => $role->is_global,
                'permission_ids' => $role->permissions->pluck('id')->values(),
                'permissions' => $role->permissions->pluck('name'),
            ]),
            'permissions' => Permission::orderBy('name')
                ->get()
                ->values()
                ->map(fn ($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                ]),
            'canManageRoles' => $user->can('create', [Role::class, $organization]),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        (new CreateRole)->handle($request->validated(), $organization, $user);

        return back()->with('success', 'Role created successfully.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        try {
            (new UpdateRole)->handle($role, $request->validated(), $request->user());

            return back()->with('success', 'Role updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization || ! $user->can('delete', [$role, $organization])) {
            abort(403);
        }

        try {
            (new DeleteRole)->handle($role, $organization, $user);

            return back()->with('success', 'Role deleted successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
