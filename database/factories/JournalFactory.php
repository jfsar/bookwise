<?php

namespace Database\Factories;

use App\Models\Journal;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(substr(str_replace(' ', '', $name), 0, 5)),
            'name' => ucwords($name),
            'description' => fake()->sentence(),
            'type' => 'general',
            'is_active' => true,
        ];
    }

    public function general(): static
    {
        return $this->state(fn () => ['type' => 'general']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
