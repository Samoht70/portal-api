<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private bool $osdd = true;

    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->migrateDatabases();

            $this->app[Kernel::class]->setArtisan(null);

            $this->updateLocalCacheOfInMemoryDatabases();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function migrateDatabases()
    {
        if (! $this->osdd) {
            $this->artisan('migrate:fresh');
        } else {
            $this->artisan('osdd:seed', ['--fresh' => true]);
        }
    }
}
