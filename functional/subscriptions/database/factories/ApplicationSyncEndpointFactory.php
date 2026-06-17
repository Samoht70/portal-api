<?php

namespace Functional\Subscriptions\Database\Factories;

use Functional\Applications\Models\Application;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationSyncEndpoint>
 */
class ApplicationSyncEndpointFactory extends Factory
{
    protected $model = ApplicationSyncEndpoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'endpoint_url' => 'https://'.fake()->domainName().'/sync',
            'secret' => Str::random(40),
            'sync_enabled' => true,
        ];
    }
}
