<?php

namespace Functional\Applications\Database\Factories;

use Functional\Applications\Models\Pack;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pack> */
class PackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
        ];
    }
}
