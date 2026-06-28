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
        // Skip XEFI: it is the canonical tenant owned by CoreReplicaSeeder (its DAILYAPPS
        // site has a fixed uuid shared with the children).
        Client::query()
            ->where('name', '!=', 'XEFI')
            ->each(function (Client $client) {
                Site::factory()
                    ->count(2)
                    ->for($client)
                    ->create();
            });

        Site::factory()
            ->for(Client::query()->where('name', 'XEFI')->first())
            ->state(['name' => 'DAILYAPPS', 'country' => 'France', 'country_alpha' => 'FR'])
            ->createOne();
    }
}
