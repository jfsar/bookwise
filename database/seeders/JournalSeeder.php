<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            $organization->journals()->updateOrCreate(
                ['code' => 'GJ'],
                [
                    'name' => 'General Journal',
                    'description' => 'Default general journal for manual entries.',
                    'type' => 'general',
                    'is_active' => true,
                ]
            );
        }
    }
}
