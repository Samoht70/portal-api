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
            ->where('name', '!=', 'DAILYAPPS')
            ->each(function (Site $site) {
                $admin = User::factory()
                    ->for($site)
                    ->administrator()
                    ->withoutManager()
                    ->createOne();

                $manager = User::factory()
                    ->for($site)
                    ->for($admin, 'directManager')
                    ->standard()
                    ->createOne();

                User::factory()
                    ->for($site)
                    ->for($manager, 'directManager')
                    ->standard()
                    ->count(3)
                    ->create();
            });

        User::factory()
            ->for(Site::query()->where('name', 'DAILYAPPS')->first())
            ->superAdmin()
            ->state(
                ['lastname' => 'Poirey', 'firstname' => 'Thomas', 'email' => 't.poirey@xefi.fr']
            )
            ->withoutManager()
            ->language('fr')
            ->create();
    }
}
