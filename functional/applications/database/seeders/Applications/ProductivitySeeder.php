<?php

namespace Functional\Applications\Database\Seeders\Applications;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\Application;
use Functional\Applications\Models\Pack;
use Illuminate\Database\Seeder;

class ProductivitySeeder extends Seeder
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
                        'name' => "Bon d'intervention",
                        'description' => "Optimisez vos bons d'interventions en un clin d'œil.",
                    ],
                    'en' => [
                        'name' => 'Work Order',
                        'description' => 'Optimize your work orders in no time.',
                    ],
                    'es' => [
                        'name' => 'Orden de intervención',
                        'description' => 'Optimiza tus órdenes de intervención en un instante.',
                    ],
                    'de' => [
                        'name' => 'Einsatzbericht',
                        'description' => 'Optimieren Sie Ihre Einsatzberichte im Handumdrehen.',
                    ],
                    'it' => [
                        'name' => 'Rapporto di intervento',
                        'description' => 'Ottimizza i tuoi rapporti di intervento in un attimo.',
                    ],
                    'nl' => [
                        'name' => 'Werkbon',
                        'description' => 'Optimaliseer uw werkbonnen in een oogwenk.',
                    ],
                ],
                'slug' => ApplicationSlug::WORK_ORDER,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Vendeur',
                        'description' => "L'outil indispensable pour booster vos ventes.",
                    ],
                    'en' => [
                        'name' => 'Salesperson',
                        'description' => 'The essential tool to boost your sales.',
                    ],
                    'es' => [
                        'name' => 'Vendedor',
                        'description' => 'La herramienta indispensable para impulsar tus ventas.',
                    ],
                    'de' => [
                        'name' => 'Verkäufer',
                        'description' => 'Das unverzichtbare Tool zur Steigerung Ihrer Verkäufe.',
                    ],
                    'it' => [
                        'name' => 'Venditore',
                        'description' => 'Lo strumento indispensabile per aumentare le tue vendite.',
                    ],
                    'nl' => [
                        'name' => 'Verkoper',
                        'description' => 'De onmisbare tool om uw verkoop te stimuleren.',
                    ],
                ],
                'slug' => ApplicationSlug::SALESPERSON,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Sales Up',
                        'description' => 'Accélérez vos performances commerciales.',
                    ],
                    'en' => [
                        'name' => 'Sales Up',
                        'description' => 'Boost your sales performance.',
                    ],
                    'es' => [
                        'name' => 'Sales Up',
                        'description' => 'Impulsa tu rendimiento comercial.',
                    ],
                    'de' => [
                        'name' => 'Sales Up',
                        'description' => 'Steigern Sie Ihre Vertriebsleistung.',
                    ],
                    'it' => [
                        'name' => 'Sales Up',
                        'description' => 'Accelera le tue performance commerciali.',
                    ],
                    'nl' => [
                        'name' => 'Sales Up',
                        'description' => 'Verhoog uw verkoopprestaties.',
                    ],
                ],
                'slug' => ApplicationSlug::SALES_UP,
            ],
        ];

        $packProductivity = Pack::query()->where('slug', 'productivity')->value('id');

        foreach ($applicationsToCreate as $applicationToCreate) {
            Application::query()
                ->updateOrCreate(
                    ['slug' => $applicationToCreate['slug']],
                    $applicationToCreate + ['pack_id' => $packProductivity]
                );
        }
    }
}
