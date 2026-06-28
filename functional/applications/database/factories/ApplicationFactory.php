<?php

namespace Functional\Applications\Database\Factories;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\Application;
use Functional\Applications\Models\Pack;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Application> */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pack_id' => Pack::factory(),
            'slug' => $this->faker->randomElement(ApplicationSlug::cases()),
        ];
    }
}
