<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachmentIndexRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller against the active
     * organization so organization context is resolved first.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the allow-listed attachable target (SRS §7.38).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', Rule::in(Attachment::attachableTypes())],
            'attachable_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
