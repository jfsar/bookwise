<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachmentStoreRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller against the active
     * organization so the organization context is resolved first.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the size, extension, and content signature of the upload plus
     * the allow-listed attachable target (SRS §0.27 file security).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', config('attachments.allowed_extensions', []));
        $maxSizeKb = (int) config('attachments.max_size_kb', 10240);

        return [
            'file' => ['required', 'file', 'max:'.$maxSizeKb, 'mimes:'.$extensions],
            'attachable_type' => ['required', 'string', Rule::in(Attachment::attachableTypes())],
            'attachable_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
