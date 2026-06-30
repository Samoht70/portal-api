<?php

namespace Functional\Applications\Database\Factories;

use Functional\Applications\Models\Pack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pack>
 */
class PackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(3, true)),
        ];
    }
}
