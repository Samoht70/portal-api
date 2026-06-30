<?php

namespace Functional\Applications\Database\Factories;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\Application;
use Functional\Applications\Models\Pack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pack_id' => Pack::factory(),
            'slug' => fake()->unique()->randomElement(ApplicationSlug::values()),
        ];
    }
}
