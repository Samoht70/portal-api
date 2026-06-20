<?php

namespace Dailyapps\PortalSync\Database\Seeders;

use Functional\Organizations\Database\Seeders\ClientSeeder;
use Functional\Organizations\Database\Seeders\SiteSeeder;
use Functional\Users\Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;

class PortalCoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ClientSeeder::class,
            SiteSeeder::class,
            UserSeeder::class,
        ]);
    }
}
