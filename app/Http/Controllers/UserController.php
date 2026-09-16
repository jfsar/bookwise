<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Actions\User\CreateUser;
use App\Actions\User\UpdateUser;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = SwitchOrganization::current($request->user());

        $members = $organization
            ? $organization->users()
                ->with(['roles' => fn ($query) => $query->wherePivot('organization_id', $organization->id)])
                ->orderBy('name')
                ->get()
                ->values()
                ->map(fn (User $member) => $this->serialize($member))
            : [];

        return Inertia::render('Settings/Users', [
            'members' => $members,
            'roles' => $organization ? $this->rolesFor($organization) : [],
            'canManageUsers' => $request->user()->can('create', [User::class, $organization]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        (new CreateUser)->handle($request->validated(), $organization, $user);

        return back()->with('success', 'User created and added to the organization.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        (new UpdateUser)->handle($user, $organization, $request->validated(), $request->user());

        return back()->with('success', 'User updated.');
    }

    /**
     * @return array<int, array{id: int, name: string, is_global: bool}>
     */
    private function rolesFor(Organization $organization): array
    {
        return Role::where(function ($query) use ($organization) {
            $query->where('organization_id', $organization->id)->orWhere('is_global', true);
        })
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_global' => $role->is_global,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'status' => $member->status,
            'last_login_at' => $member->last_login_at?->diffForHumans(),
            'roles' => $member->roles
                ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])
                ->values(),
        ];
    }
}
