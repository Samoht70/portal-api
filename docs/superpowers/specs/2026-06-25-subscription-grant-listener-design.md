# Subscription : capture par events Eloquent + nudge-to-pull sur grant

Date : 2026-06-25
Branche : `feat/sync-simplification`

## Contexte

La simplification sync (2026-06-20) a câblé toute la capture mère→enfants sur des
**listeners liés directement aux events Eloquent**, dans le boot du provider, pilotés
par `config/sync.php` :

```php
// EventDistributionServiceProvider::bootOutboxCapture()
foreach (SyncAggregates::models() as $model) {
    $model::created(RecordAggregateUpserted::class);
    $model::updated(RecordAggregateUpserted::class);
    $model::deleted(RecordAggregateDeleted::class);
}
```

`Subscription` est resté en dehors de ce pattern : il dispatche au **niveau du modèle**
via `$dispatchesEvents`, et la simplification n'a été appliquée qu'à moitié.

### Deux problèmes constatés

1. **Dispatch au niveau du modèle** (`Subscription::$dispatchesEvents`) au lieu d'un
   listener lié directement à l'event Eloquent dans le provider — incohérent avec le
   reste du sync.
2. **`created => SubscriptionGranted` est du code mort** : rien n'écoute
   `SubscriptionGranted` (seul `SubscriptionRevoked → PurgeOnRevoke` est câblé). Le doc
   `ARCHITECTURE-SYNC.md` dit même que `SubscriptionGranted`/`BackfillOnGrant` sont
   *supprimés* (un grant est rattrapé par le pull snapshot) — mais le modèle dispatche
   encore l'event dans le vide.

### Décision

On veut **un listener sur la création** d'une subscription, après tout. Approche retenue :
**nudge-to-pull** — la mère pousse un event de contrôle `subscription.granted` au nouveau
souscripteur, et l'enfant déclenche son pull scopé immédiatement (au lieu d'attendre le
reconcile planifié). Symétrique de `PurgeOnRevoke`, réutilise le chemin snapshot existant.

## Changements

### A. Côté mère — `functional/subscriptions`

**`Models/Subscription.php`**
- Supprimer `protected $dispatchesEvents` et les imports `SubscriptionGranted` / `SubscriptionRevoked`.
- Aucun helper de scope ajouté : la lecture du scope reste dans le trait `CarriesSubscriptionScope`
  (voir ci-dessous), pas sur le modèle — on ne fait pas fuiter la préoccupation sync dans
  le modèle de domaine.

**`Listeners/Concerns/CarriesSubscriptionScope.php` (déplacé + refactoré)**
- Aujourd'hui le trait lit `$this->subscription` (propriété d'event). On le déplace depuis
  `Events/Concerns/` vers `Listeners/Concerns/` et il prend désormais le modèle en argument,
  de sorte que les deux listeners (qui reçoivent la `Subscription` dans `handle()`) le mixent :
  ```php
  namespace Functional\Subscriptions\Listeners\Concerns;

  use Functional\Subscriptions\Models\Subscription;

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

**`Providers/SubscriptionsServiceProvider.php`**
- `registerSyncListeners()` :
  ```php
  Subscription::created(PullOnGrant::class);
  Subscription::deleted(PurgeOnRevoke::class);
  ```
- Retirer `Event::listen(...)`, l'import de la façade `Event` et l'import `SubscriptionRevoked`.

**`Listeners/PullOnGrant.php` (nouveau)** — symétrique de `PurgeOnRevoke` :
- `use CarriesSubscriptionScope;`
- `const string EVENT_TYPE = 'subscription.granted';`
- ctor : `private SyncDirectory $directory`
- `handle(Subscription $subscription): void`
  - `$subscriber = $this->directory->applicationFor($this->applicationId($subscription));`
  - si `null` → return
  - `$clientId = $this->clientId($subscription);`
  - `EventEnvelope::wrap(... aggregateType: 'subscription', eventType: self::EVENT_TYPE, tenantScope: $clientId, payload: ['client_id' => $clientId] ...)`
  - `DeliverDomainEvent::dispatch($envelope, $subscriber->endpointUrl, $subscriber->secret);`
  - **Pas** d'écriture dans l'outbox `domain_events` (event de contrôle livré directement, comme revoke).

**`Listeners/PurgeOnRevoke.php`** — passe à une liaison directe sur l'event modèle :
- `use CarriesSubscriptionScope;`
- `handle(Subscription $subscription): void` (au lieu de `SubscriptionRevoked $event`)
- lit le scope via `$this->clientId($subscription)` / `$this->applicationId($subscription)`.

**Suppressions**
- `Events/SubscriptionGranted.php`
- `Events/SubscriptionRevoked.php`
- `Events/Concerns/` (le trait `CarriesSubscriptionScope` est déplacé vers `Listeners/Concerns/`, pas supprimé)

### B. Côté mère — `dailyapps/event-distribution`

**`Http/Controllers/SyncSnapshot.php`** — nouveau filtre tenant optionnel :
- Lire `$tenant = $request->query('tenant')`.
- Si présent : valider `$tenant ∈ $scope->clientIds` (sinon `abort(403)`), puis scoper la
  requête à ce seul client — `$class::syncSnapshotQuery([$tenant])` au lieu de
  `$scope->clientIds`.
- Si absent : comportement actuel inchangé (tout le scope). Les params `since`/`cursor`
  restent disponibles et orthogonaux.

### C. Côté enfant — `dailyapps/portal-sync-client`

**`Http/Controllers/HandleDomainEvent.php`**
- `const string GRANT_EVENT_TYPE = 'subscription.granted';`
- Nouveau cas, après la vérif HMAC + fenêtre de rejeu, avant l'upsert d'état — symétrique
  du cas revoke, on lit le tenant dans l'enveloppe (`payload['client_id']`, comme purge) :
  ```php
  if ($envelope['event_type'] === self::GRANT_EVENT_TYPE) {
      PullTenant::dispatch((string) $envelope['payload']['client_id']);
      return response()->json(['status' => 'pulling']);
  }
  ```
- Réponse immédiate : le pull tourne en file, pas dans la requête webhook.

**`Jobs/PullTenant.php` (nouveau)** — pull ciblé d'un seul tenant :
- ctor `(private string $clientId)` ; injecte `ReplicaWriter` + `MotherSyncClient` dans `handle()`.
- Pour chaque `config('portal-sync.snapshot_types')` : pagine
  `GET /api/sync/snapshot?type=<type>&tenant=<clientId>` (via `MotherSyncClient`) et
  `$writer->apply($type, $row)` sur chaque ligne.
- **Pas de `since`** (état courant complet du tenant) et **pas de tombstone** (un grant
  n'enlève rien). Idempotent via le gate `_sync_sequence` du writer.
- Logique de pagination identique à `ReconcileFromMother::pull()` mais sans `since`/tombstone
  et avec le param `tenant` — pas de réutilisation de `sync:reconcile`, dont le delta est
  par ailleurs bugué (voir « Hors périmètre »).

## Tests

- **Mère `SubscriptionLifecycleTest`** : `test_granting_a_subscription_pushes_nothing`
  devient `test_granting_a_subscription_notifies_the_subscriber_to_pull` — assert
  `DeliverDomainEvent` poussé 1× avec `event_type === 'subscription.granted'`,
  `tenant_scope === $client->getKey()`, `payload['client_id'] === $client->getKey()`.
  Mettre à jour le docblock de classe. Les deux tests revoke restent verts (câblage
  `deleted` inchangé fonctionnellement).
- **Enfant `HandleDomainEventTest`** : nouveau test — un body `subscription.granted`
  signé met `PullTenant` en file avec le `client_id` de l'enveloppe (`Queue::fake()` +
  assertion sur l'arg) et renvoie 2xx ; un mauvais HMAC reste rejeté (401).
- **Enfant `PullTenant` (nouveau test)** : avec le snapshot mère mocké (`Http::fake()`),
  le job pagine `?tenant=<client_id>` par type et upsert chaque ligne ; aucune requête
  ne porte `since`, aucune ligne hors tenant n'est tombstonée.
- **Mère `SyncSnapshot` (test du filtre tenant)** : `?tenant=` dans le scope ne renvoie que
  ce tenant ; `?tenant=` hors scope → 403 ; sans `tenant`, comportement actuel inchangé.
- Lancer toute la suite des layers touchés (`php artisan osdd:phpunit` puis phpunit ciblé
  sur `subscriptions`, `event-distribution`, `portal-sync-client`).

## Doc à corriger

`technical/ARCHITECTURE-SYNC.md` — passages qui affirment que grant ne pousse rien et que
`SubscriptionGranted`/`BackfillOnGrant` sont supprimés (≈ lignes 402, 491-492, 525-528) :
réécrire pour décrire le nudge-to-pull (`subscription.granted` → pull ciblé `PullTenant`
sur `GET /api/sync/snapshot?tenant=<client_id>`, sans `since` ni tombstone).

## Hors périmètre

- Relais outbox, `scopeFor`, HMAC, sémantique revoke côté purge : inchangés. Le seul ajout
  à l'endpoint snapshot est le filtre `tenant` optionnel (rétro-compatible).
- **Bug du watermark `sync:reconcile` (incrément séparé)** : le delta non-`--full` calcule
  `since`/la convergence checksum à partir de `max(updated_at)` local, pollué dès que
  l'enfant écrit ses propres colonnes sur une ligne réplica (`since` trop récent → lignes
  mère ratées ; checksum jamais convergent → reconcile en boucle). Correctif prévu hors de
  ce spec : colonne `_sync_updated_at` (horloge mère, alimentée par `ReplicaWriter`) utilisée
  pour `since` **et** la comparaison checksum. Le pull ciblé du grant n'en dépend pas (pas de
  `since`), donc cet incrément avance indépendamment.
