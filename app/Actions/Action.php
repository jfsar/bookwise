<?php

namespace App\Actions;

use Illuminate\Support\Facades\Validator;

trait Action
{
    /**
     * Get validation rules for this action.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Run the action with validation.
     */
    public function run(array $data, mixed ...$args): mixed
    {
        $validated = Validator::make($data, $this->rules())->validate();

        return $this->handle($validated, ...$args);
    }
}
