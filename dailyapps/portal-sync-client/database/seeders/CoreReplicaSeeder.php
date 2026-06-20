<?php

namespace Dailyapps\PortalSync\Database\Seeders;

use Dailyapps\PortalSync\Support\MotherSyncClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class CoreReplicaSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->shouldBootstrapFromMother()) {
            $this->command?->info('portal-sync: bootstrapping the core from the mother…');
            Artisan::call('sync:bootstrap', [], $this->command?->getOutput());

            return;
        }

        $this->call(PortalCoreSeeder::class);
    }

    private function shouldBootstrapFromMother(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if (! config('portal-sync.replica')) {
            return false;
        }

        $mother = app(MotherSyncClient::class);

        if (! $mother->isConfigured()) {
            return false;
        }

        try {
            $mother->get('/api/sync/checksum?type=clients');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
