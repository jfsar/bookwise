<?php

namespace App\Http\Requests;

use App\Actions\Organization\SwitchOrganization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = SwitchOrganization::current($user);

        if (! $user || ! $organization) {
            return false;
        }

        /** @var User|null $target */
        $target = $this->route('user');

        return $target instanceof User && $user->can('update', [$target, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', 'in:active,inactive'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var User $user */
            $user = $this->route('user');

            if ($user->id === $this->user()->id && $this->input('status') === 'inactive') {
                $validator->errors()->add('status', 'You cannot deactivate your own account.');
            }
        });
    }
}
