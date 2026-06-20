<?php

namespace Functional\Subscriptions\Console\Commands;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\search;

class LinkSyncSubscriber extends Command
{
    protected $signature = 'sync:link-subscriber
        {application? : Application id or slug (e.g. business-card); omit to pick interactively}
        {--endpoint= : Inbound webhook URL (default http://<slug>/api/sync/events)}
        {--client=* : Client id to subscribe (repeatable); omit to only create the endpoint}
        {--secret= : HMAC secret (a random one is generated when omitted)}
        {--disabled : Create the endpoint with sync disabled}';

    protected $description = 'Provision a child sync endpoint + subscription to test the mother→child data sync.';

    private const string ALL_CLIENTS = '__all__';

    public function handle(): int
    {
        $application = $this->resolveApplicationFromInput();

        if ($application === null) {
            return self::FAILURE;
        }

        $endpoint = $this->option('endpoint') ?: "http://{$application->slug->value}/api/sync/events";
        $secret = $this->option('secret') ?: Str::random(64);

        $endpointForeignKey = (new ApplicationSyncEndpoint)->application()->getForeignKeyName();

        ApplicationSyncEndpoint::query()->updateOrCreate(
            [$endpointForeignKey => $application->getKey()],
            [
                'endpoint_url' => $endpoint,
                'secret' => $secret,
                'sync_enabled' => ! $this->option('disabled'),
            ],
        );

        $this->subscribeClients($application);

        $this->info('Sync endpoint provisioned.');

        $this->renderChildEnv($application->getKey(), $secret);

        return self::SUCCESS;
    }

    private function subscribeClients(Application $application): void
    {
        $clientForeignKey = (new Subscription)->client()->getForeignKeyName();
        $applicationForeignKey = (new Subscription)->application()->getForeignKeyName();

        $clientIds = (array) $this->option('client');

        if ($clientIds === []) {
            $clientId = $this->promptForClient();

            if ($clientId === null) {
                $this->warn('No client subscribed — only the endpoint was provisioned.');

                return;
            }

            $clientIds = [$clientId];
        }

        if (in_array(self::ALL_CLIENTS, $clientIds, true)) {
            $clientIds = Client::query()->pluck((new Client)->getKeyName())->all();
        }

        foreach ($clientIds as $clientId) {
            Subscription::query()->firstOrCreate([
                $clientForeignKey => $clientId,
                $applicationForeignKey => $application->getKey(),
            ]);

            $this->line("Subscribed client {$clientId}.");
        }
    }

    private function promptForClient(): ?string
    {
        if (! $this->input->isInteractive()) {
            $this->warn('No --client given and the console is non-interactive; skipping subscription.');

            return null;
        }

        if (Client::query()->doesntExist()) {
            $this->warn('No clients exist to subscribe.');

            return null;
        }

        return search(
            label: 'Which client should be subscribed?',
            options: fn (string $value) => [
                self::ALL_CLIENTS => 'All clients (everyone in the database)',
                ...Client::query()
                    ->when($value !== '', fn ($query) => $query->where('name', 'like', "%{$value}%"))
                    ->orderBy('name')
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->all(),
            ],
            placeholder: 'Type to search by name…',
        );
    }

    private function renderChildEnv(string $applicationId, string $secret): void
    {
        $this->newLine();
        $this->line('# Child replica .env (paste into the subscribed app):');
        $this->line('PORTAL_SYNC_REPLICA=true');
        $this->line('PORTAL_SYNC_MOTHER_URL=http://portal-api');
        $this->line('PORTAL_SYNC_APPLICATION_ID='.$applicationId);
        $this->line('PORTAL_SYNC_SECRET='.$secret);
        $this->line('PORTAL_SYNC_SNAPSHOT_TYPES='.implode(',', array_keys(config('sync.aggregates', []))));
    }

    private function resolveApplicationFromInput(): ?Application
    {
        $argument = $this->argument('application');

        if ($argument !== null) {
            $application = $this->resolveApplication($argument);

            if ($application === null) {
                $this->error('Unknown application — pass a valid id or one of: '.implode(', ', ApplicationSlug::values()));
            }

            return $application;
        }

        return $this->promptForApplication();
    }

    private function promptForApplication(): ?Application
    {
        if (! $this->input->isInteractive()) {
            $this->error('The application argument is required when the console is non-interactive.');

            return null;
        }

        $applicationId = search(
            label: 'Which application is the child?',
            options: fn (string $value) => Application::query()
                ->when($value !== '', fn ($query) => $query->where('slug', 'like', "%{$value}%"))
                ->orderBy('slug')
                ->limit(20)
                ->get(['id', 'slug'])
                ->mapWithKeys(fn (Application $application) => [
                    $application->getKey() => $application->slug->value,
                ])
                ->all(),
            placeholder: 'Type to search by slug…',
        );

        return Application::query()->whereKey($applicationId)->first();
    }

    private function resolveApplication(string $value): ?Application
    {
        $slug = ApplicationSlug::tryFrom($value);

        if ($slug !== null) {
            return Application::query()->where('slug', $slug->value)->first();
        }

        return Application::query()->whereKey($value)->first();
    }
}
