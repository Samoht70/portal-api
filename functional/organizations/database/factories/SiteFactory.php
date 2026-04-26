<?php

namespace Functional\Organizations\Database\Factories;

use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->company(),
            'country' => $this->faker->country(),
            'country_alpha' => $this->faker->countryCode(),
        ];
    }
}
