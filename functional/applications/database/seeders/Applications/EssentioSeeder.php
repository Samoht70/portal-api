<?php

namespace Functional\Applications\Database\Seeders\Applications;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\Application;
use Functional\Applications\Models\Pack;
use Illuminate\Database\Seeder;

class EssentioSeeder extends Seeder
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
                    'en' => [
                        'name' => 'Business Card',
                        'description' => 'Your digital business card always within reach.',
                    ],
                    'es' => [
                        'name' => 'Tarjeta de visita',
                        'description' => 'Tu tarjeta de visita digital siempre a mano.',
                    ],
                    'de' => [
                        'name' => 'Visitenkarte',
                        'description' => 'Ihre digitale Visitenkarte jederzeit griffbereit.',
                    ],
                    'it' => [
                        'name' => 'Biglietto da visita',
                        'description' => 'Il tuo biglietto da visita digitale sempre a portata di mano.',
                    ],
                    'nl' => [
                        'name' => 'Visitekaartje',
                        'description' => 'Je digitale visitekaartje altijd binnen handbereik.',
                    ],
                ],
                'slug' => ApplicationSlug::BUSINESS_CARD,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Congés',
                        'description' => "La gestion de vos congés n'a jamais été aussi simple.",
                    ],
                    'en' => [
                        'name' => 'Leave Management',
                        'description' => 'Managing your leave has never been easier.',
                    ],
                    'es' => [
                        'name' => 'Gestión de vacaciones',
                        'description' => 'Gestionar tus vacaciones nunca ha sido tan fácil.',
                    ],
                    'de' => [
                        'name' => 'Urlaubsverwaltung',
                        'description' => 'Die Verwaltung Ihrer Urlaube war noch nie so einfach.',
                    ],
                    'it' => [
                        'name' => 'Gestione ferie',
                        'description' => 'Gestire le tue ferie non è mai stato così semplice.',
                    ],
                    'nl' => [
                        'name' => 'Verlofbeheer',
                        'description' => 'Het beheren van uw verlof was nog nooit zo eenvoudig.',
                    ],
                ],
                'slug' => ApplicationSlug::LEAVE_MANAGEMENT,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Guide Collaborateurs',
                        'description' => 'Intégrez votre entreprise en toute fluidité.',
                    ],
                    'en' => [
                        'name' => 'Employee Guide',
                        'description' => 'Onboard your employees smoothly.',
                    ],
                    'es' => [
                        'name' => 'Guía del empleado',
                        'description' => 'Integra a tus empleados de forma fluida.',
                    ],
                    'de' => [
                        'name' => 'Mitarbeiterhandbuch',
                        'description' => 'Integrieren Sie Ihre Mitarbeitenden reibungslos.',
                    ],
                    'it' => [
                        'name' => 'Guida dipendenti',
                        'description' => 'Integra i tuoi collaboratori senza difficoltà.',
                    ],
                    'nl' => [
                        'name' => 'Medewerkersgids',
                        'description' => 'Integreer uw medewerkers moeiteloos.',
                    ],
                ],
                'slug' => ApplicationSlug::EMPLOYEE_GUIDE,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Note de frais',
                        'description' => 'Gérez vos notes de frais, où que vous soyez.',
                    ],
                    'en' => [
                        'name' => 'Expense Reports',
                        'description' => 'Manage your expense reports wherever you are.',
                    ],
                    'es' => [
                        'name' => 'Gastos',
                        'description' => 'Gestiona tus gastos estés donde estés.',
                    ],
                    'de' => [
                        'name' => 'Spesenabrechnung',
                        'description' => 'Verwalten Sie Ihre Spesen, wo immer Sie sind.',
                    ],
                    'it' => [
                        'name' => 'Nota spese',
                        'description' => 'Gestisci le tue note spese ovunque ti trovi.',
                    ],
                    'nl' => [
                        'name' => 'Onkostennota',
                        'description' => 'Beheer uw onkostennota’s waar u ook bent.',
                    ],
                ],
                'slug' => ApplicationSlug::EXPENSE_REPORTS,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Questionnaire',
                        'description' => 'Créez et partagez vos questionnaires en 3 clics.',
                    ],
                    'en' => [
                        'name' => 'Survey',
                        'description' => 'Create and share your surveys in 3 clicks.',
                    ],
                    'es' => [
                        'name' => 'Cuestionario',
                        'description' => 'Crea y comparte tus cuestionarios en 3 clics.',
                    ],
                    'de' => [
                        'name' => 'Fragebogen',
                        'description' => 'Erstellen und teilen Sie Ihre Umfragen in 3 Klicks.',
                    ],
                    'it' => [
                        'name' => 'Questionario',
                        'description' => 'Crea e condividi i tuoi questionari in 3 clic.',
                    ],
                    'nl' => [
                        'name' => 'Vragenlijst',
                        'description' => 'Maak en deel uw vragenlijsten in 3 klikken.',
                    ],
                ],
                'slug' => ApplicationSlug::SURVEY,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Émargement',
                        'description' => 'Suivez en temps réel la présence de vos invités.',
                    ],
                    'en' => [
                        'name' => 'Attendance Tracking',
                        'description' => 'Track your guests attendance in real time.',
                    ],
                    'es' => [
                        'name' => 'Control de asistencia',
                        'description' => 'Sigue la asistencia de tus invitados en tiempo real.',
                    ],
                    'de' => [
                        'name' => 'Anwesenheitsliste',
                        'description' => 'Verfolgen Sie die Anwesenheit Ihrer Gäste in Echtzeit.',
                    ],
                    'it' => [
                        'name' => 'Registro presenze',
                        'description' => 'Monitora la presenza dei tuoi ospiti in tempo reale.',
                    ],
                    'nl' => [
                        'name' => 'Aanwezigheidsregistratie',
                        'description' => 'Volg de aanwezigheid van uw gasten in realtime.',
                    ],
                ],
                'slug' => ApplicationSlug::ATTENDANCE_TRACKING,
            ],
            [
                'translations' => [
                    'fr' => [
                        'name' => 'Covoiturage',
                        'description' => 'Organisez facilement les trajets partagés de vos équipes.',
                    ],
                    'en' => [
                        'name' => 'Carpooling',
                        'description' => 'Easily organize shared rides for your teams.',
                    ],
                    'es' => [
                        'name' => 'Coche compartido',
                        'description' => 'Organiza fácilmente los viajes compartidos de tus equipos.',
                    ],
                    'de' => [
                        'name' => 'Fahrgemeinschaft',
                        'description' => 'Organisieren Sie Fahrgemeinschaften für Ihre Teams ganz einfach.',
                    ],
                    'it' => [
                        'name' => 'Car pooling',
                        'description' => 'Organizza facilmente i viaggi condivisi del tuo team.',
                    ],
                    'nl' => [
                        'name' => 'Carpoolen',
                        'description' => 'Organiseer eenvoudig gedeelde ritten voor uw teams.',
                    ],
                ],
                'slug' => ApplicationSlug::CARPOOLING,
            ],
        ];

        $packEssentio = Pack::query()->where('slug', 'essentio')->value('id');

        foreach ($applicationsToCreate as $applicationToCreate) {
            Application::query()
                ->updateOrCreate(
                    ['slug' => $applicationToCreate['slug']],
                    $applicationToCreate + ['pack_id' => $packEssentio]
                );
        }
    }
}
