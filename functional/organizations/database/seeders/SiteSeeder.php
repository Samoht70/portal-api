<?php

namespace Functional\Organizations\Database\Seeders;

use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::query()
            ->each(function (Client $client) {
                Site::factory()
                    ->count(2)
                    ->for($client)
                    ->create();
            });

        Site::factory()
            ->for(Client::query()->where('name', 'XEFI')->first())
            ->state(['name' => 'DAILYAPPS'])
            ->createOne();
    }
}
