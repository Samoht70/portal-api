<?php

namespace Functional\Applications\Database\Seeders\Applications;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\Application;
use Functional\Applications\Models\Pack;
use Illuminate\Database\Seeder;

class AutoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applicationsToCreate = [
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Carte de visite',
                        'description' => 'Votre carte de visite digitale à portée de main.',
                    ],
                    'en' => [],
                    'es' => [],
                    'de' => [],
                    'it' => [],
                    'nl' => [],
                ],
                'slug' => ApplicationSlug::BUSINESS_CARD,
            ],
        ];

        $packAuto = Pack::query()->where('slug', 'auto')->value('id');

        foreach ($applicationsToCreate as $applicationToCreate) {
            Application::query()
                ->updateOrCreate(
                    ['slug' => $applicationToCreate['slug']],
                    $applicationToCreate + ['pack_id' => $packAuto]
                );
        }
    }
}
