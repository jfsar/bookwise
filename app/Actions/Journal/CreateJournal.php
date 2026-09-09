<?php

namespace App\Actions\Journal;

use App\Actions\Action;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\User;

class CreateJournal
{
    use Action;

    public function handle(array $data, Organization $organization, User $creator): Journal
    {
        return Journal::create([
            'organization_id' => $organization->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'general',
            'is_active' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
