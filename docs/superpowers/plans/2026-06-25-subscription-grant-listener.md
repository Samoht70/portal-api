# Subscription Grant Listener — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le dispatch au niveau du modèle `Subscription` par des listeners liés directement aux events Eloquent, et ajouter un listener sur la création qui déclenche un pull ciblé du nouveau tenant chez l'enfant (nudge-to-pull).

**Architecture:** Côté mère, `Subscription::created`/`deleted` sont câblés dans le provider vers `PullOnGrant`/`PurgeOnRevoke` (comme `event-distribution` câble ses agrégats). `PullOnGrant` pousse un event de contrôle `subscription.granted` (mêmes `tenant_scope` + `payload['client_id']` que le revoke). L'enfant, à réception, lance un job `PullTenant` qui pull l'état courant de ce seul tenant via l'endpoint snapshot doté d'un nouveau filtre `tenant`. Aucun `since`, aucun tombstone.

**Tech Stack:** Laravel (PHP 8.5), PHPUnit, OSDD layers, Eloquent model events, queued jobs, HMAC-signed sync webhooks.

## Global Constraints

- **Spec de référence :** `docs/superpowers/specs/2026-06-25-subscription-grant-listener-design.md`.
- **Tests dans le conteneur :** toutes les commandes `php artisan` / tests tournent via `make shell p=portal-api` (PHP 8.5 du projet).
- **Tests par layer :** chaque layer porte ses tests dans son propre `tests/`. Après ajout d'un fichier de test, lancer `php artisan osdd:phpunit` pour resynchroniser `phpunit.xml`.
- **Conventions Xefi :** `->getKey()` plutôt que `->id` ; FK via `relation()->getForeignKeyName()` (jamais en dur) ; un `use` de trait par ligne ; code et commentaires en anglais.
- **Pas d'attribution IA** dans le code ni dans les messages de commit (pas de trailer `Co-Authored-By`, pas de mention d'outil). Messages de commit : une seule ligne de sujet.
- **Commentaires** : une phrase simple suffit ; pas de docblock multi-lignes verbeux.
- **Hors périmètre (ne PAS toucher) :** le bug du watermark `sync:reconcile` (colonne `_sync_updated_at`) part dans un incrément séparé. `scopeFor`, `RelayDomainEvents`, HMAC : inchangés.

---

### Task 1 : Mère — dispatch modèle → liaison directe + suppression du code mort

Refactor à comportement constant : on retire `$dispatchesEvents`, on lie `PurgeOnRevoke` directement sur l'event Eloquent `deleted`, on déplace/refactore le trait de scope, et on supprime les deux events de domaine (dont `SubscriptionGranted`, qui était dispatché dans le vide). Le filet de sécurité, ce sont les tests `SubscriptionLifecycleTest` existants, qui doivent rester verts (y compris `test_granting_a_subscription_pushes_nothing` : sans `$dispatchesEvents`, une création ne pousse toujours rien — le grant sera traité en Task 2).

**Files:**
- Modify: `functional/subscriptions/src/Models/Subscription.php`
- Create: `functional/subscriptions/src/Listeners/Concerns/CarriesSubscriptionScope.php`
- Create: `functional/subscriptions/src/Listeners/Concerns/PushesSubscriptionControlEvent.php`
- Delete: `functional/subscriptions/src/Events/Concerns/CarriesSubscriptionScope.php`
- Delete: `functional/subscriptions/src/Events/SubscriptionGranted.php`
- Delete: `functional/subscriptions/src/Events/SubscriptionRevoked.php`
- Modify: `functional/subscriptions/src/Listeners/PurgeOnRevoke.php`
- Modify: `functional/subscriptions/src/Providers/SubscriptionsServiceProvider.php`
- Test (safety net, existing): `functional/subscriptions/tests/Feature/SubscriptionLifecycleTest.php`

**Interfaces:**
- Produces:
  - trait `Functional\Subscriptions\Listeners\Concerns\CarriesSubscriptionScope` exposant `private function clientId(Subscription): string` et `private function applicationId(Subscription): string`.
  - trait `Functional\Subscriptions\Listeners\Concerns\PushesSubscriptionControlEvent` (compose `CarriesSubscriptionScope`) exposant `private function pushControlEvent(Subscription $subscription, string $eventType): void` ; il s'appuie sur la propriété `$this->directory` (`SyncDirectory`) du listener qui l'utilise.
  - `PurgeOnRevoke::handle(Subscription $subscription): void` ; `PurgeOnRevoke::EVENT_TYPE = 'subscription.revoked'` (inchangé).
- Consumes: `SyncDirectory::applicationFor()`, `EventEnvelope::wrap()`, `DeliverDomainEvent::dispatch()` (inchangés).

- [ ] **Step 1 : Vérifier que les tests existants passent (baseline verte)**

Run: `php artisan test --filter SubscriptionLifecycleTest`
Expected: PASS (3 tests), dont `test_granting_a_subscription_pushes_nothing` et les deux tests revoke.

- [ ] **Step 2 : Créer le trait déplacé et refactoré**

Create `functional/subscriptions/src/Listeners/Concerns/CarriesSubscriptionScope.php` :

```php
<?php

namespace Functional\Subscriptions\Listeners\Concerns;

use Functional\Subscriptions\Models\Subscription;

/**
 * Reads a subscription's client/application ids via its relations for the sync listeners.
 */
trait CarriesSubscriptionScope
{
    private function clientId(Subscription $subscription): string
    {
        return $subscription->getAttribute($subscription->client()->getForeignKeyName());
    }

    private function applicationId(Subscription $subscription): string
    {
        return $subscription->getAttribute($subscription->application()->getForeignKeyName());
    }
}
```

Create `functional/subscriptions/src/Listeners/Concerns/PushesSubscriptionControlEvent.php` — l'émission partagée par les deux listeners (résolution du souscripteur + enveloppe + dispatch). Il lit le scope via `CarriesSubscriptionScope` et s'appuie sur la propriété `$this->directory` que chaque listener déclare :

```php
<?php

namespace Functional\Subscriptions\Listeners\Concerns;

use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Support\Str;

/**
 * Pushes a subscription control event to the affected subscriber, when one exists.
 */
trait PushesSubscriptionControlEvent
{
    use CarriesSubscriptionScope;

    private function pushControlEvent(Subscription $subscription, string $eventType): void
    {
        $subscriber = $this->directory->applicationFor($this->applicationId($subscription));

        if ($subscriber === null) {
            return;
        }

        $clientId = $this->clientId($subscription);
        $sequence = (int) (DomainEventRecord::query()->max('sequence') ?? 0);

        $envelope = EventEnvelope::wrap(
            id: (string) Str::uuid7(),
            sequence: $sequence,
            aggregateType: 'subscription',
            aggregateId: $clientId,
            eventType: $eventType,
            tenantScope: $clientId,
            occurredAt: now()->toIso8601String(),
            payload: ['client_id' => $clientId],
        );

        DeliverDomainEvent::dispatch($envelope, $subscriber->endpointUrl, $subscriber->secret);
    }
}
```

- [ ] **Step 3 : Supprimer l'ancien trait et les deux events**

```bash
git rm functional/subscriptions/src/Events/Concerns/CarriesSubscriptionScope.php \
       functional/subscriptions/src/Events/SubscriptionGranted.php \
       functional/subscriptions/src/Events/SubscriptionRevoked.php
```

(Le dossier `Events/Concerns/` devient vide ; le `git rm` le retire de l'index. Le dossier `Events/` reste, il accueillera d'éventuels events futurs.)

- [ ] **Step 4 : Nettoyer le modèle**

Modify `functional/subscriptions/src/Models/Subscription.php` — retirer `protected $dispatchesEvents` et les deux `use` d'events. Résultat attendu du fichier :

```php
<?php

namespace Functional\Subscriptions\Models;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;

#[UseFactory(SubscriptionFactory::class)]
#[Fillable(['client_id', 'application_id', 'licenses'])]
class Subscription extends Model
{
    use HasControl;
    use HasFactory;
    use HasUuids;

    protected function casts(): array
    {
        return [
            'licenses' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
```

- [ ] **Step 5 : Adapter `PurgeOnRevoke` (reçoit le modèle, mixe le trait)**

Replace the full content of `functional/subscriptions/src/Listeners/PurgeOnRevoke.php` :

```php
<?php

namespace Functional\Subscriptions\Listeners;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Functional\Subscriptions\Listeners\Concerns\PushesSubscriptionControlEvent;
use Functional\Subscriptions\Models\Subscription;

final readonly class PurgeOnRevoke
{
    use PushesSubscriptionControlEvent;

    public const string EVENT_TYPE = 'subscription.revoked';

    public function __construct(private SyncDirectory $directory) {}

    public function handle(Subscription $subscription): void
    {
        $this->pushControlEvent($subscription, self::EVENT_TYPE);
    }
}
```

- [ ] **Step 6 : Câbler la liaison directe dans le provider**

Modify `functional/subscriptions/src/Providers/SubscriptionsServiceProvider.php` :
- Retirer les `use` de `Illuminate\Support\Facades\Event` et `Functional\Subscriptions\Events\SubscriptionRevoked`.
- Remplacer le corps de `registerSyncListeners()` :

```php
    private function registerSyncListeners(): void
    {
        Subscription::deleted(PurgeOnRevoke::class);
    }
```

(Le `use Functional\Subscriptions\Models\Subscription;` et `use Functional\Subscriptions\Listeners\PurgeOnRevoke;` sont déjà présents.)

- [ ] **Step 7 : Relancer la baseline — elle doit rester verte**

Run: `php artisan test --filter SubscriptionLifecycleTest`
Expected: PASS (3 tests). `test_granting_a_subscription_pushes_nothing` passe toujours (création ne pousse rien), et les deux tests revoke passent via la liaison directe `Subscription::deleted`.

- [ ] **Step 8 : Commit**

```bash
git add functional/subscriptions/src/Models/Subscription.php \
        functional/subscriptions/src/Listeners/Concerns/CarriesSubscriptionScope.php \
        functional/subscriptions/src/Listeners/Concerns/PushesSubscriptionControlEvent.php \
        functional/subscriptions/src/Listeners/PurgeOnRevoke.php \
        functional/subscriptions/src/Providers/SubscriptionsServiceProvider.php \
        functional/subscriptions/src/Events
git commit -m "♻️ refactor(subscriptions): Bind sync listeners directly on Eloquent events"
```

---

### Task 2 : Mère — listener `PullOnGrant` sur la création

Sur `Subscription::created`, pousser un event de contrôle `subscription.granted` au souscripteur (mêmes `tenant_scope` + `payload['client_id']` que le revoke).

**Files:**
- Create: `functional/subscriptions/src/Listeners/PullOnGrant.php`
- Modify: `functional/subscriptions/src/Providers/SubscriptionsServiceProvider.php`
- Test: `functional/subscriptions/tests/Feature/SubscriptionLifecycleTest.php`

**Interfaces:**
- Produces: `PullOnGrant::handle(Subscription $subscription): void` ; `PullOnGrant::EVENT_TYPE = 'subscription.granted'`.
- Consumes: trait `PushesSubscriptionControlEvent` (Task 1) ; `SyncDirectory` (injecté).

- [ ] **Step 1 : Réécrire le test du grant (échoue)**

Dans `functional/subscriptions/tests/Feature/SubscriptionLifecycleTest.php` :
- Mettre à jour le docblock de classe pour décrire le nudge-to-pull.
- Remplacer `test_granting_a_subscription_pushes_nothing` par :

```php
    public function test_granting_a_subscription_notifies_the_subscriber_to_pull(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://child.test/sync',
            'secret' => 'grant-key',
            'sync_enabled' => true,
        ]);

        Queue::fake();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        Queue::assertPushed(DeliverDomainEvent::class, 1);
        Queue::assertPushed(DeliverDomainEvent::class, function (DeliverDomainEvent $job) use ($client) {
            $envelope = $this->envelopeOf($job);

            return $envelope['event_type'] === PullOnGrant::EVENT_TYPE
                && $envelope['payload']['client_id'] === $client->getKey()
                && $envelope['tenant_scope'] === $client->getKey();
        });
    }
```

Ajouter l'import en tête de fichier : `use Functional\Subscriptions\Listeners\PullOnGrant;`

- [ ] **Step 2 : Lancer le test — il échoue**

Run: `php artisan test --filter test_granting_a_subscription_notifies_the_subscriber_to_pull`
Expected: FAIL — classe `PullOnGrant` introuvable (et aucun `DeliverDomainEvent` poussé).

- [ ] **Step 3 : Créer le listener `PullOnGrant`**

Create `functional/subscriptions/src/Listeners/PullOnGrant.php` :

```php
<?php

namespace Functional\Subscriptions\Listeners;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Functional\Subscriptions\Listeners\Concerns\PushesSubscriptionControlEvent;
use Functional\Subscriptions\Models\Subscription;

/**
 * Pushes a `subscription.granted` control event so the new subscriber pulls that tenant now.
 */
final readonly class PullOnGrant
{
    use PushesSubscriptionControlEvent;

    public const string EVENT_TYPE = 'subscription.granted';

    public function __construct(private SyncDirectory $directory) {}

    public function handle(Subscription $subscription): void
    {
        $this->pushControlEvent($subscription, self::EVENT_TYPE);
    }
}
```

- [ ] **Step 4 : Câbler la création dans le provider**

Modify `registerSyncListeners()` dans `SubscriptionsServiceProvider.php` :

```php
    private function registerSyncListeners(): void
    {
        Subscription::created(PullOnGrant::class);
        Subscription::deleted(PurgeOnRevoke::class);
    }
```

Ajouter `use Functional\Subscriptions\Listeners\PullOnGrant;` aux imports.

- [ ] **Step 5 : Lancer le test — il passe**

Run: `php artisan test --filter SubscriptionLifecycleTest`
Expected: PASS (3 tests : le nouveau grant + les deux revoke).

- [ ] **Step 6 : Commit**

```bash
git add functional/subscriptions/src/Listeners/PullOnGrant.php \
        functional/subscriptions/src/Providers/SubscriptionsServiceProvider.php \
        functional/subscriptions/tests/Feature/SubscriptionLifecycleTest.php
git commit -m "✨ feat(subscriptions): Notify subscriber to pull on grant"
```

---

### Task 3 : Mère — filtre `tenant` sur l'endpoint snapshot

`GET /api/sync/snapshot?type=X&tenant=<client_id>` restreint la requête à un seul tenant, validé contre le scope du demandeur.

**Files:**
- Modify: `dailyapps/event-distribution/src/Http/Controllers/SyncSnapshot.php`
- Test: `dailyapps/event-distribution/tests/Feature/SyncSnapshotTest.php`

**Interfaces:**
- Produces: l'endpoint snapshot accepte un query param optionnel `tenant` ; `403` si `tenant ∉ scope.clientIds` ; sans `tenant`, comportement actuel inchangé.
- Consumes: `SnapshotScope::$clientIds`, `SyncAggregates::modelFor()`, `$class::syncSnapshotQuery(array $clientIds)`.

- [ ] **Step 1 : Écrire les tests du filtre (échouent)**

Ajouter à `dailyapps/event-distribution/tests/Feature/SyncSnapshotTest.php`. Note : `subscribe()` n'inscrit qu'un client ; pour un second client dans le scope, on crée une 2e `Subscription` sur la même application.

```php
    public function test_snapshot_tenant_filter_returns_only_that_tenant(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $applicationId = $this->subscribe($clientA);

        // Second in-scope client on the same application.
        Subscription::factory()->create([
            'client_id' => $clientB->getKey(),
            'application_id' => $applicationId,
        ]);

        $siteA = Site::factory()->create(['client_id' => $clientA->getKey()]);
        $siteB = Site::factory()->create(['client_id' => $clientB->getKey()]);

        $response = $this->signedGet('/api/sync/snapshot?type=sites&tenant='.$clientA->getKey(), $applicationId);

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($siteA->getKey(), $ids);
        $this->assertNotContains($siteB->getKey(), $ids);
    }

    public function test_snapshot_tenant_outside_scope_is_forbidden(): void
    {
        $subscribed = Client::factory()->create();
        $foreign = Client::factory()->create();
        $applicationId = $this->subscribe($subscribed);

        $response = $this->signedGet('/api/sync/snapshot?type=sites&tenant='.$foreign->getKey(), $applicationId);

        $response->assertStatus(403);
    }
```

- [ ] **Step 2 : Lancer les tests — ils échouent**

Run: `php artisan test --filter SyncSnapshotTest`
Expected: FAIL — `test_snapshot_tenant_filter_returns_only_that_tenant` renvoie aussi `siteB` (pas de filtre), et `test_snapshot_tenant_outside_scope_is_forbidden` renvoie 200 au lieu de 403.

- [ ] **Step 3 : Implémenter le filtre dans le contrôleur**

Modify `dailyapps/event-distribution/src/Http/Controllers/SyncSnapshot.php` — entre la résolution de `$class` (après le `abort(422)`) et la construction de `$query`, insérer la résolution du scope filtré, puis l'utiliser :

```php
        $clientIds = $scope->clientIds;
        $tenant = $request->query('tenant');

        if ($tenant !== null) {
            if (! in_array($tenant, $clientIds, true)) {
                abort(403);
            }

            $clientIds = [$tenant];
        }

        $query = $class::syncSnapshotQuery($clientIds);
```

(Remplace la ligne existante `$query = $class::syncSnapshotQuery($scope->clientIds);`.)

- [ ] **Step 4 : Lancer les tests — ils passent**

Run: `php artisan test --filter SyncSnapshotTest`
Expected: PASS (tous les tests, dont les deux nouveaux et les anciens — sans `tenant`, le comportement est inchangé).

- [ ] **Step 5 : Commit**

```bash
git add dailyapps/event-distribution/src/Http/Controllers/SyncSnapshot.php \
        dailyapps/event-distribution/tests/Feature/SyncSnapshotTest.php
git commit -m "✨ feat(sync): Add scope-validated tenant filter to snapshot endpoint"
```

---

### Task 4 : Enfant — job `PullTenant`

Pull ciblé de l'état courant d'un seul tenant via `?tenant=<client_id>`, sans `since` ni tombstone.

**Files:**
- Create: `dailyapps/portal-sync-client/src/Jobs/PullTenant.php`
- Test: `dailyapps/portal-sync-client/tests/Feature/PullTenantTest.php`

**Interfaces:**
- Produces: `PullTenant` (queued job), constructeur `(string $clientId)`, `handle(ReplicaWriter $writer, MotherSyncClient $mother): void`.
- Consumes: `MotherSyncClient::get(string $path): array`, `ReplicaWriter::apply(string $type, array $row): void`, `config('portal-sync.snapshot_types')`.

- [ ] **Step 1 : Écrire le test (échoue)**

Create `dailyapps/portal-sync-client/tests/Feature/PullTenantTest.php` :

```php
<?php

namespace Dailyapps\PortalSync\Tests\Feature;

use Dailyapps\PortalSync\Jobs\PullTenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PullTenantTest extends TestCase
{
    private const string SECRET = 's3cret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal-sync.replica' => true,
            'portal-sync.mother_url' => 'https://mother.test',
            'portal-sync.application_id' => 'app-1',
            'portal-sync.sync_secret' => self::SECRET,
            'portal-sync.snapshot_types' => ['replica_sites'],
        ]);

        Schema::dropIfExists('replica_sites');
        Schema::create('replica_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('_sync_sequence')->default(0);
            $table->uuid('_sync_tenant')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('replica_sites');

        parent::tearDown();
    }

    public function test_it_pulls_the_tenant_scoped_snapshot_without_since(): void
    {
        $clientId = (string) Str::uuid();
        $now = now()->toDateTimeString();

        $row = [
            'id' => (string) Str::uuid(),
            'name' => 'Acme HQ',
            '_sync_sequence' => 7,
            '_sync_tenant' => $clientId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        Http::fake([
            'https://mother.test/api/sync/snapshot*' => Http::response([
                'data' => [$row],
                'next_cursor' => null,
            ]),
        ]);

        app()->call([new PullTenant($clientId), 'handle']);

        $stored = DB::table('replica_sites')->where('id', $row['id'])->first();
        $this->assertNotNull($stored);
        $this->assertSame('Acme HQ', $stored->name);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tenant='.$clientId)
            && ! str_contains($request->url(), 'since='));
    }
}
```

- [ ] **Step 2 : Lancer le test — il échoue**

Run: `php artisan test dailyapps/portal-sync-client/tests/Feature/PullTenantTest.php`
Expected: FAIL — classe `PullTenant` introuvable.

- [ ] **Step 3 : Créer le job**

Create `dailyapps/portal-sync-client/src/Jobs/PullTenant.php` :

```php
<?php

namespace Dailyapps\PortalSync\Jobs;

use Dailyapps\PortalSync\Ingestion\ReplicaWriter;
use Dailyapps\PortalSync\Support\MotherSyncClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pulls a single tenant's current state from the mother into the replica (no since, no tombstone).
 */
class PullTenant implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $clientId) {}

    public function handle(ReplicaWriter $writer, MotherSyncClient $mother): void
    {
        foreach (config('portal-sync.snapshot_types', []) as $type) {
            $cursor = null;

            do {
                $path = '/api/sync/snapshot?type='.$type.'&tenant='.rawurlencode($this->clientId);

                if ($cursor !== null) {
                    $path .= '&cursor='.rawurlencode($cursor);
                }

                $page = $mother->get($path);

                foreach ($page['data'] as $row) {
                    $writer->apply($type, $row);
                }

                $cursor = $page['next_cursor'] ?? null;
            } while ($cursor !== null);
        }
    }
}
```

- [ ] **Step 4 : Lancer le test — il passe**

Run: `php artisan test dailyapps/portal-sync-client/tests/Feature/PullTenantTest.php`
Expected: PASS (1 test). La requête porte `tenant=<client_id>` et pas de `since` ; la ligne est upsertée.

- [ ] **Step 5 : Commit**

```bash
git add dailyapps/portal-sync-client/src/Jobs/PullTenant.php \
        dailyapps/portal-sync-client/tests/Feature/PullTenantTest.php
git commit -m "✨ feat(portal-sync): Add tenant-scoped PullTenant job"
```

---

### Task 5 : Enfant — `HandleDomainEvent` réagit à `subscription.granted`

À réception de `subscription.granted`, lire `payload['client_id']` (comme le purge) et mettre `PullTenant` en file.

**Files:**
- Modify: `dailyapps/portal-sync-client/src/Http/Controllers/HandleDomainEvent.php`
- Test: `dailyapps/portal-sync-client/tests/Feature/HandleDomainEventTest.php`

**Interfaces:**
- Produces: `HandleDomainEvent::GRANT_EVENT_TYPE = 'subscription.granted'` ; à réception, dispatch `PullTenant($payload['client_id'])`.
- Consumes: `PullTenant` (Task 4).

- [ ] **Step 1 : Écrire le test du cas grant (échoue)**

Dans `dailyapps/portal-sync-client/tests/Feature/HandleDomainEventTest.php` :
- Ajouter les imports : `use Dailyapps\PortalSync\Jobs\PullTenant;` et `use Illuminate\Support\Facades\Queue;`
- Ajouter une fabrique d'enveloppe grant à côté de `revokeEnvelope()` :

```php
    /**
     * @return array<string, mixed>
     */
    private function grantEnvelope(string $clientId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'sequence' => 100,
            'aggregate_type' => 'subscription',
            'aggregate_id' => $clientId,
            'event_type' => HandleDomainEvent::GRANT_EVENT_TYPE,
            'tenant_scope' => $clientId,
            'occurred_at' => now()->toIso8601String(),
            'schema_version' => 1,
            'payload' => ['client_id' => $clientId],
        ];
    }
```

- Ajouter le test :

```php
    public function test_a_grant_control_event_queues_a_tenant_pull(): void
    {
        Queue::fake();

        $clientId = (string) Str::uuid();

        $response = $this->dispatch($this->grantEnvelope($clientId));

        $this->assertSame('pulling', $response->getData(true)['status']);

        Queue::assertPushed(PullTenant::class, function (PullTenant $job) use ($clientId) {
            return (fn () => $this->clientId)->call($job) === $clientId;
        });
    }
```

- [ ] **Step 2 : Lancer le test — il échoue**

Run: `php artisan test dailyapps/portal-sync-client/tests/Feature/HandleDomainEventTest.php --filter test_a_grant_control_event_queues_a_tenant_pull`
Expected: FAIL — constante `GRANT_EVENT_TYPE` indéfinie / `status` ≠ `pulling` (l'event tombe dans le chemin d'upsert).

- [ ] **Step 3 : Implémenter le cas grant dans le contrôleur**

Modify `dailyapps/portal-sync-client/src/Http/Controllers/HandleDomainEvent.php` :
- Ajouter l'import `use Dailyapps\PortalSync\Jobs\PullTenant;`
- Ajouter la constante sous `REVOKE_EVENT_TYPE` :

```php
    /**
     * Wire event type for the grant control signal (matches the mother's PullOnGrant::EVENT_TYPE).
     */
    public const string GRANT_EVENT_TYPE = 'subscription.granted';
```

- Ajouter le branchement juste après le bloc `REVOKE_EVENT_TYPE`, avant la construction du `$payload` d'upsert :

```php
        if ($envelope['event_type'] === self::GRANT_EVENT_TYPE) {
            PullTenant::dispatch((string) $envelope['payload']['client_id']);

            return response()->json(['status' => 'pulling']);
        }
```

- [ ] **Step 4 : Lancer les tests du contrôleur — ils passent**

Run: `php artisan test dailyapps/portal-sync-client/tests/Feature/HandleDomainEventTest.php`
Expected: PASS (tous les tests, dont le nouveau grant et le rejet 401 inchangé).

- [ ] **Step 5 : Commit**

```bash
git add dailyapps/portal-sync-client/src/Http/Controllers/HandleDomainEvent.php \
        dailyapps/portal-sync-client/tests/Feature/HandleDomainEventTest.php
git commit -m "✨ feat(portal-sync): Pull tenant on subscription.granted control event"
```

---

### Task 6 : Doc — `ARCHITECTURE-SYNC.md` + suite complète

Aligner la doc d'architecture sur le nouveau comportement, puis valider l'ensemble.

**Files:**
- Modify: `technical/ARCHITECTURE-SYNC.md`

- [ ] **Step 1 : Resynchroniser les testsuites et lancer les layers touchés**

Run:
```bash
php artisan osdd:phpunit
php artisan test --testsuite "functional/subscriptions" --testsuite "dailyapps/event-distribution"
php artisan test dailyapps/portal-sync-client/tests
```
Expected: PASS sur les trois périmètres.

- [ ] **Step 2 : Corriger la doc**

Modify `technical/ARCHITECTURE-SYNC.md` aux endroits qui affirment que le grant ne pousse rien et que `SubscriptionGranted`/`BackfillOnGrant` sont supprimés (≈ lignes 402, 491-492, 525-528) :
- Décision 6 / cycle de souscription : remplacer « un grant est rattrapé par le pull snapshot » et « les jobs `BackfillSubscriber` / listener `BackfillOnGrant` / l'event `SubscriptionGranted` sont supprimés » par la description du nudge-to-pull :
  > Un **grant** émet un event de contrôle `subscription.granted` (`PullOnGrant`) → l'enfant lance un **pull ciblé** du tenant (`PullTenant`) via `GET /api/sync/snapshot?tenant=<client_id>` (sans `since`, sans tombstone). Symétrique du `revoke`/`PurgeOnRevoke`.
- Mentionner le nouveau filtre `tenant` (validé contre le scope) dans la section endpoints sync.
- Si pertinent, noter en marge le bug connu du watermark `sync:reconcile` (delta `since` pollué par les écritures enfant) comme correctif à venir hors de cet incrément.

- [ ] **Step 3 : Commit**

```bash
git add technical/ARCHITECTURE-SYNC.md
git commit -m "📝 docs(sync): Document grant nudge-to-pull and snapshot tenant filter"
```

---

## Notes d'exécution

- Toutes les commandes tournent dans le conteneur (`make shell p=portal-api`).
- Ordre des tâches : 1 → 2 (mère) puis 3 (endpoint) puis 4 → 5 (enfant) puis 6 (doc/validation). La Task 4 doit précéder la Task 5 (qui consomme `PullTenant`). La Task 3 doit précéder les tests d'intégration enfant qui supposent le filtre `tenant`.
- Le bug du watermark `sync:reconcile` est délibérément hors périmètre (incrément séparé).
