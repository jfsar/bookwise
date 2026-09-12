<?php

namespace App\Http\Requests;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = SwitchOrganization::current($user);

        if (! $user || ! $organization) {
            return false;
        }

        return $user->can('create', [Role::class, $organization]);
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
