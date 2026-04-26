<?php

namespace Functional\Users\Database\Seeders;

use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        Site::query()
            ->each(function (Site $site) {
                User::factory()
                    ->for($site)
                    ->administrator()
                    ->createOne();

                User::factory()
                    ->for($site)
                    ->standard()
                    ->count(4)
                    ->create();
            });

        User::factory()
            ->for(Site::query()->where('name', 'DAILYAPPS')->first())
            ->superAdmin()
            ->state(['lastname' => 'Poirey', 'firstname' => 'Thomas', 'email' => 't.poirey@xefi.fr'])
            ->createOne();
    }
}
