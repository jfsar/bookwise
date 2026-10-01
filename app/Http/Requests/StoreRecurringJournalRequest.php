<?php

namespace App\Http\Requests;

use App\Actions\Organization\SwitchOrganization;
use App\Enums\RecurringJournalFrequency;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringJournalRequest extends FormRequest
{
    protected ?Organization $organization = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->organization();
        $orgId = $organization?->id;

        $existsInOrg = fn (string $table) => $orgId !== null
            ? Rule::exists($table, 'id')->where('organization_id', $orgId)
            : Rule::exists($table, 'id');

        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'journal_id' => ['required', 'integer', $existsInOrg('journals')],
            'frequency' => ['required', Rule::enum(RecurringJournalFrequency::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_submit' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', $existsInOrg('accounts')],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    protected function organization(): ?Organization
    {
        return $this->organization ??= SwitchOrganization::current($this->user());
    }
}
