<?php

namespace Functional\Users\Database\Factories;

use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'manager_id' => faker()->boolean() ? User::factory() : null,
            'lastname' => faker()->lastName(),
            'firstname' => faker()->firstName(),
            'email' => faker()->email(),
            'password' => 'password',
            'language' => faker()->randomElement(['fr', 'en', 'de', 'es', 'it', 'nl']),
        ];
    }

    public function superAdmin(): static
    {
        return $this
            ->afterCreating(function (User $user) {
                $user->assignRole('super-admin');
            });
    }

    public function administrator(): static
    {
        return $this
            ->afterCreating(function (User $user) {
                $user->assignRole('administrator');
            });
    }

    public function standard(): static
    {
        return $this
            ->afterCreating(function (User $user) {
                $user->assignRole('standard');
            });
    }
}
