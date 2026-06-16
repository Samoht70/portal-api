<?php

namespace Dailyapps\PortalShared\Schema;

use Illuminate\Database\Schema\Blueprint;

/**
 * Single source of truth for the shared identity/org core columns.
 *
 * These Blueprint macros define the canonical columns for the portal users,
 * clients, and sites tables. They are consumed by the mother app's functional
 * layers and by child replicas alike, guaranteeing both sides describe the same
 * core schema.
 */
class PortalSchema
{
    const int SCHEMA_VERSION = 1;

    public static function registerMacros(): void
    {
        Blueprint::macro('portalUsers', function (): Blueprint {
            /** @var Blueprint $this */
            $this->uuid('id')->primary();
            $this->string('lastname');
            $this->string('firstname');
            $this->string('email')->unique();
            $this->string('language')->nullable();
            $this->timestamps();

            return $this;
        });

        Blueprint::macro('portalClients', function (): Blueprint {
            /** @var Blueprint $this */
            $this->uuid('id')->primary();
            $this->string('name');
            $this->timestamps();

            return $this;
        });

        Blueprint::macro('portalSites', function (): Blueprint {
            /** @var Blueprint $this */
            $this->uuid('id')->primary();
            $this->string('name');
            $this->string('country');
            $this->string('country_alpha');
            $this->string('subdivision')->nullable();
            $this->string('subdivision_code', 2)->nullable();
            $this->timestamps();

            return $this;
        });

        Blueprint::macro('portalForeignId', function (string $model, ?string $column = null, bool $nullable = false): Blueprint {
            /** @var Blueprint $this */
            $foreignId = $this->foreignIdFor($model, $column);

            if ($nullable) {
                $foreignId->nullable();
            }

            $foreignId->constrained();

            return $this;
        });
    }
}
