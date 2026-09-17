<?php

namespace App\Http\Requests;

use App\Actions\Organization\SwitchOrganization;
use App\Enums\ReportType;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    protected ?Organization $organization = null;

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
            'report_type' => ['sometimes', 'required', 'string', Rule::in(array_column(ReportType::cases(), 'value'))],
            'account_id' => ['nullable', 'integer', $existsInOrg('accounts')],
            'journal_id' => ['nullable', 'integer', $existsInOrg('journals')],
            'fiscal_year_id' => ['nullable', 'integer', $existsInOrg('fiscal_years')],
            'accounting_period_id' => ['nullable', 'integer', $existsInOrg('accounting_periods')],
            'reference' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'as_of' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return collect($this->validated())
            ->except(['report_type'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    protected function organization(): ?Organization
    {
        return $this->organization ??= SwitchOrganization::current($this->user());
    }
}
