<?php

namespace App\Http\Requests;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = SwitchOrganization::current($user);

        if (! $user || ! $organization) {
            return false;
        }

        /** @var Role|null $role */
        $role = $this->route('role');

        return $role instanceof Role && $user->can('update', [$role, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
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
