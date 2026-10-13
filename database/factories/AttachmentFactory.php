<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'attachable_type' => JournalEntry::class,
            'attachable_id' => JournalEntry::factory(),
            'original_name' => $this->faker->word().'.'.$this->faker->fileExtension(),
            'stored_name' => Str::uuid().'.'.$this->faker->fileExtension(),
            'disk' => 'local',
            'path' => 'attachments/'.$this->faker->word(),
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(100, 10000),
            'visibility' => 'private',
            'scan_status' => 'clean',
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
