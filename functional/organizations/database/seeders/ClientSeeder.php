<?php

namespace Functional\Organizations\Database\Seeders;

use Functional\Organizations\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::factory()
            ->count(5)
            ->create();

        Client::factory()
            ->state(['name' => 'XEFI'])
            ->createOne();
    }
}
