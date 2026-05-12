<?php

namespace Functional\Applications\Database\Seeders;

use Functional\Applications\Models\Pack;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packsToCreate = [
            [
                'translations' => [
                    'fr' => ['name' => 'Offre Essentio'],
                    'en' => ['name' => 'Essentio Offer'],
                    'es' => ['name' => 'Oferta Essentio'],
                    'de' => ['name' => 'Essentio Angebot'],
                    'it' => ['name' => 'Offerta Essentio'],
                    'nl' => ['name' => 'Essentio Offerte'],
                ],
                'slug' => 'essentio',
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Offre Productivity'],
                    'en' => ['name' => 'Productivity Offer'],
                    'es' => ['name' => 'Oferta Productivity'],
                    'de' => ['name' => 'Productivity Angebot'],
                    'it' => ['name' => 'Offerta Productivity'],
                    'nl' => ['name' => 'Productivity Offerte'],
                ],
                'slug' => 'productivity',
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Options Essentio'],
                    'en' => ['name' => 'Essentio Addon'],
                    'es' => ['name' => 'Opciones Essentio'],
                    'de' => ['name' => 'Essentio Addon'],
                    'it' => ['name' => 'Essentio Addon'],
                    'nl' => ['name' => 'Essentio Addon'],
                ],
                'slug' => 'essentio-addon',
            ],
            [
                'translations' => [
                    'fr' => ['name' => 'Offre Automobile'],
                    'en' => ['name' => 'Auto Offer'],
                    'es' => ['name' => 'Oferta Automobile'],
                    'de' => ['name' => 'Auto Angebot'],
                    'it' => ['name' => 'Offerta Automobile'],
                    'nl' => ['name' => 'Auto Offerte'],
                ],
                'slug' => 'auto',
            ]
        ];

        foreach ($packsToCreate as $packToInsert) {
            Pack::query()
                ->updateOrCreate(
                    ['slug' => $packToInsert['slug']],
                    $packToInsert
                );
        }
    }
}
