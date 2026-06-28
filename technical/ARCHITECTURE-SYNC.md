# Architecture — Synchronisation de données App mère → Apps enfants

> Document de décision d'architecture. **Conception validée et implémentée** (incréments
> 1–5, cf. plan en fin de doc). Sujet central : faut-il un réplica local, et comment
> **arrêter de dupliquer les schémas de tables** entre la mère et les enfants. Complète
> [`ARCHITECTURE-SSO.md`](./ARCHITECTURE-SSO.md).
>
> **Révisé le 2026-06-16** (confronté au code) : « enfant » = `Application` (1:1
> `oauth_client`) ; routage tenant via un M2M explicite `Client ↔ Application` dans un
> nouveau layer `functional/subscriptions` (remplace l'ancien lien `oauth_clients↔client_id`
> et la table `sync_subscriptions`).
>
> **Révisé le 2026-06-17** : la **capture** de l'outbox passe des « classes Action par
> mutation » aux **events de cycle de vie Eloquent + listeners synchrones** (interface
> `SyncableAggregate` + trait `SyncsToReplica`, listeners `RecordAggregate*`, écrivain
> `DomainEventRecorder`). Couvre le `/mutate` lomkit et l'Eloquent courant, conforme à
> `laravel:no-observers`, reconcile en filet pour les écritures de masse. Les Actions ne
> servent plus qu'aux **events sémantiques**. Détail en Décision 3.1.
>
> **Révisé le 2026-06-20 — simplification majeure** (l'architecture livrée diffère des
> révisions précédentes ; ce doc décrit l'état réel) :
> - **Plus de package de schéma partagé.** `dailyapps/portal-shared-schema` (macros de
>   Blueprint `portalUsers`/`portalClients`/`portalSites`/`portalForeignId`,
>   modèles abstraits, snapshot de dérive, SemVer expand/contract) est **supprimé**. Le
>   **payload JSON EST le contrat**, figé par un test côté mère
>   (`dailyapps/event-distribution/tests/Feature/PayloadContractTest.php`). Chaque enfant
>   écrit **ses propres migrations** ; son `ReplicaWriter` ignore les colonnes inconnues.
>   Les migrations mère (`create_clients_table`, `create_sites_table`, `create_users_table`)
>   sont redevenues **auto-portées** (colonnes inline, aucune macro). Détail en Décision 2.
> - **Idempotence + ordre = une seule règle par ligne.** Chaque ligne réplica porte
>   `_sync_sequence` (upsert conditionnel atomique : on applique ssi `sequence` entrant >
>   stocké) et `_sync_tenant` (purge générique d'un tenant révoqué). Cela **remplace** les
>   tables `processed_events`, `replica_sync_state` et le plancher `last_applied_sequence`
>   par agrégat.
> - **Deux chemins de données** (plus trois) : push live (webhook) + **un seul pull
>   snapshot** servant à l'amorçage, au reconcile et au pull ciblé sur grant. Un **grant**
>   émet un event de contrôle `subscription.granted` (`PullOnGrant`) → l'enfant lance un
>   **pull ciblé** du tenant (`PullTenant`) via `GET /api/sync/snapshot?tenant=<client_id>`
>   (sans `since`, sans tombstone) — symétrique du revoke/`PurgeOnRevoke`. Un **revoke** émet
>   **un** event de contrôle `subscription.revoked` → l'enfant purge par `_sync_tenant`.
> - **Câblage par config.** `SyncableRegistry` (runtime) → `config/sync.php` (map
>   `aggregates`) lue via `SyncAggregates`. Les **deux** interfaces de résolution
>   (`SubscriberResolver` + `SnapshotResolver`) sont fusionnées en **une**
>   `Dailyapps\EventDistribution\Contracts\SyncDirectory` (implémentée par
>   `Functional\Subscriptions\Resolver\SyncDirectoryFromSubscriptions`). L'endpoint
>   `/api/sync/watermark` est **supprimé** (chaque ligne de snapshot porte son plancher).
> - **Nouveau package enfant.** L'ingestion vit désormais dans
>   `dailyapps/portal-sync-client` (namespace `Dailyapps\PortalSync`), **ingestion seule,
>   aucun DDL, aucune macro** : `HandleDomainEvent`, `ReplicaWriter`, `MotherSyncClient`,
>   commandes `sync:bootstrap`/`sync:reconcile`, trait `ReadOnlyReplica`.

## Contexte

`portal-api` est l'application **mère** : un fournisseur d'identité OAuth2/OIDC
**stateless** (Passport + `jeremy379/laravel-openid-connect`), bâti en **OSDD**
(chaque domaine = un *layer* = un package composer sous `functional/` ou `technical/`,
avec ses migrations, modèles, ressources REST lomkit, contrôles d'accès, seeders,
service provider).

Aujourd'hui les apps enfants ne reçoivent que **l'identité** via les claims du JWT.
Le **noyau identité/orga** dont elles ont besoin localement — `users` (`site_id`,
`manager_id` (FK auto-référente, pas une table), email, noms, langue), `clients`,
`sites`, et plus tard `professions` — n'a **aucun mécanisme de synchronisation**.
Conséquence actuelle : **ces schémas sont recopiés à la main dans chaque enfant**, ce
qui dérive et coûte cher.

### Vocabulaire / acteurs (sans ambiguïté)

| Terme | Signification concrète dans le code |
|---|---|
| **Mère** | `portal-api` (ce repo). IdP OAuth2/OIDC. **Seul écrivain** du noyau identité/orga. |
| **Enfant** | Une **app aval** (ex. RH, notes de frais) qui délègue l'auth à la mère et garde un **réplica local**. Se matérialise par une ligne du **catalogue `Application`** (`functional/applications`), liée **1:1 à un `oauth_client`** Passport. « Enfant » = **`Application`**, pas `oauth_client`. |
| **`Client`** = **org** = **tenant** | Une organisation cliente (entreprise). **Unité d'isolation des données.** Les trois mots sont synonymes dans ce doc. |
| **`User`** | Une personne. `User → Site → Client` (FK `site_id`, puis `sites.client_id`). |
| **Souscription** | Le couple **(org `Client` × app `Application`)** : « cette org utilise cette app ». **N'existe pas encore** → créé dans le nouveau layer `functional/subscriptions`. C'est la **clé de routage** de la sync. |

> Deux « multi » à ne pas confondre : (a) la **mère est déjà multi-tenant** — `technical/access-control`
> scope déjà par `client_id` via `ClientPerimeter` ; (b) **un enfant sert plusieurs orgs et une org
> souscrit à plusieurs apps** → relation **many-to-many `Client ↔ Application`** (décision ci-dessous).

Cadrage :
- Enfants = **Laravel/OSDD homogènes**.
- Fraîcheur attendue = **quasi temps réel (push)**.
- **Périmètre RÉPLIQUÉ = noyau identité/orga seulement** (`users`, `clients`, `sites`,
  `professions`). Le **catalogue applicatif** (`Application`, `Pack`, `RoleDefinition`,
  `ApplicationRole`) **n'est PAS répliqué** : les enfants l'obtiennent via les claims
  OIDC ou à la demande.
- **`Application` entre quand même dans le jeu — mais comme identité du *souscripteur* et
  clé de *routage*, jamais comme donnée répliquée.** La sync route un événement scopé sur
  une org `Client` vers **toutes les `Application` souscrites à cette org** (M2M
  `Client ↔ Application`, layer `functional/subscriptions`). Réplication du catalogue ≠
  routage par le catalogue : les deux cohabitent sans contradiction.
- **Forme « variable selon l'enfant »** : chaque enfant veut un sous-ensemble/sur-ensemble
  de colonnes différent → chaque enfant écrit ses propres migrations ; le payload JSON sert
  de contrat commun et l'ingestion ignore les colonnes que l'enfant ne déclare pas.
- Toutes les entités sont en **UUID + SoftDeletes** (point clé : pas de remapping d'ID
  entre mère et enfants).
- **Horizon + Redis** déjà en place ; aucun event/observer/job/webhook n'existe encore
  (terrain vierge propre).

---

## Décision 1 — Quel modèle de distribution ? (réplica vs cache vs lecture directe)

| Option | Fraîcheur | Survit à une panne de la mère | Isolation tenant | Charge de maintenance | Verdict |
|---|---|---|---|---|---|
| **A. Réplica local complet** (tables locales alimentées par push) | Quasi temps réel | **Oui** | Forte (filtrage par livraison) | Faible une fois construit | **✅ Recommandé** |
| B. Cache léger invalidé sur événement (stocke des IDs, refetch à la lecture) | Quasi temps réel | Partielle (cache froid ⇒ dépend de la mère) | Forte | Moyenne | ❌ dépend encore de la mère |
| C. Lecture directe à la demande (API REST de la mère à chaque requête) | Temps réel | **Non** — panne mère = authz HS partout | Forte | Schéma nul, latence forte | ❌ couplage + latence |

**Recommandation : A — réplica local complet par enfant, alimenté par un pipeline de
push, avec C utilisé uniquement pour l'amorçage/backfill et un *reconcile* périodique
comme filet de sécurité.**

Pourquoi A et pas C/B :
- Les enfants doivent **faire des jointures locales** (`users.site_id`, le pivot
  rôles↔users) pour leur propre autorisation, sur des chemins chauds → la lecture
  directe ajoute une latence réseau inacceptable à chaque requête.
- L'identité/les rôles doivent **continuer à fonctionner si la mère est indisponible**.
  Seul un réplica local le garantit.
- Le cache (B) reste dépendant de la mère au moindre cache-miss → ne résout pas la
  résilience.

> Le réplica n'est **plus maintenu à la main** (la douleur actuelle) : il est alimenté
> par le pipeline ci-dessous, et son contenu est garanti par **le contrat de payload**
> (Décision 2), pas par une recopie de schéma.

---

## Décision 2 — Comment ARRÊTER de dupliquer les schémas de tables

C'est le cœur du problème. **La douleur n'était pas le schéma SQL en soi, mais le fait de
le maintenir synchronisé à la main.** Une première conception (révisions 06-16/06-17)
partageait un **package de schéma** (`dailyapps/portal-shared-schema` : macros de
Blueprint `portalUsers()`/`portalClients()`/…, helper `portalForeignId()`, modèles
abstraits read-only, constante `SCHEMA_VERSION`, snapshot de dérive en CI, SemVer
expand/contract). **Cette approche a été abandonnée le 2026-06-20** : elle imposait à mère
et enfants la même DDL physique (alors que chaque enfant veut un jeu de colonnes
différent), forçait une discipline de versioning lourde, et couplait l'enfant au code de
schéma de la mère pour une table qu'il pourrait définir trivialement lui-même.

### Le mécanisme retenu : le payload JSON EST le contrat

On ne partage **ni schéma, ni macro, ni modèle**. Le seul contrat entre mère et enfant est
**la forme du payload JSON** émis pour chaque agrégat. Concrètement :

- **Côté mère**, chaque agrégat syncable produit son payload via `toSyncPayload()` (défaut
  = `attributesToArray()`, qui respecte `$hidden` → jamais `password`). Ce
  payload est **figé par un test** : `dailyapps/event-distribution/tests/Feature/PayloadContractTest.php`
  vérifie que `clients`/`sites`/`users` portent bien leurs clés de contrat (`id`, `name`,
  `client_id`, `site_id`, `manager_id`, timestamps, `deleted_at`…) et **qu'aucun secret
  n'y figure**. Retirer/renommer une clé de contrat fait rougir ce test : c'est là, et nulle
  part dans une constante de version, que se garde la compatibilité.
- **Côté enfant**, chaque enfant écrit **ses propres migrations** (la table `clients` qu'il
  veut, avec ses colonnes locales). Son `ReplicaWriter` fait
  `array_intersect_key($payload, Schema::getColumnListing($table))` : **il ignore
  silencieusement toute clé du payload qui n'a pas de colonne locale**. Un enfant n'a donc
  besoin de stocker que ce qu'il consomme ; une nouvelle clé côté mère n'oblige personne à
  migrer.

> Pas de catalogue applicatif ici (hors périmètre). Le périmètre répliqué reste minuscule
> (`clients`, `sites`, `users`).

Ce qui reste **local à chaque enfant** : ses migrations de tables réplica (deux colonnes
techniques obligatoires : `_sync_sequence` et `_sync_tenant`, cf. Décision 3.3), son
service provider, et ses consommateurs de sync. **L'enfant ne dépend que de
`dailyapps/portal-sync-client`** (runtime d'ingestion), jamais d'un package de schéma.

Ce qui reste **local à la mère** : ses layers fonctionnels « gros » inchangés (seul
écrivain). Leurs migrations sont **auto-portées** : `create_clients_table`,
`create_sites_table`, `create_users_table` posent leurs colonnes en clair (plus aucun appel
de macro). Mère et enfants ne partagent plus de DDL — ils ne peuvent diverger que sur des
colonnes que l'enfant a choisi de ne pas répliquer, ce qui est précisément le but.

### Règle d'appartenance des migrations (respect de ARCHITECTURE-SSO.md)

La règle maison « les colonnes suivent le layer propriétaire de leur table ; un layer
technique ne migre jamais la table d'un layer fonctionnel » est **respectée trivialement** :
- Côté mère, chaque table vit dans la migration de son layer fonctionnel propriétaire
  (`organizations` pour `clients`/`sites`, `users` pour `users`).
- Côté enfant, il n'existe pas de layer fonctionnel concurrent : l'enfant porte légitimement
  ses propres migrations réplica.
- Pas de collision : **bases de données différentes** (DB mère vs DB de chaque enfant), et
  aucun fichier de migration n'est partagé.

> Plus de garde anti-dérive de schéma (le snapshot SQLite) : il n'y a plus de schéma commun
> à comparer. La garde est désormais le `PayloadContractTest` côté mère.

---

## Décision 3 — Le pipeline de push (quasi temps réel, fiable)

### 3.1 Capture — outbox transactionnel alimenté par les events Eloquent

> **Révisé le 2026-06-17.** La version initiale prescrivait une **classe Action par
> mutation** (`RenameSite`…) comme seul point d'écriture de l'outbox. Confronté au code,
> ça ne tient pas : l'essentiel des écritures passe par le **`/mutate` générique de
> lomkit** (qui fait `->save()` dans sa propre transaction) et par de l'**Eloquent
> courant**, qui ne traversent aucune Action maison. On capture donc **au niveau
> persistance**, via les **events de cycle de vie Eloquent + listeners** — mécanisme
> **sanctionné** par `laravel:no-observers` (qui bannit les *Observer*, mais **impose**
> events+listeners câblés en service provider via `Model::created(...)`/`::updated(...)`).

Les 4 objections initiales aux events, réévaluées :
- *« se déclenchent même si la transaction rollback »* → **levée** : le listener (synchrone)
  n'écrit qu'une **ligne outbox sur la même connexion**, donc **dans la transaction du
  `save()`** ; elle rollback avec la mutation. La livraison reste asynchrone (le relais lit
  des lignes déjà *committées*).
- *« aucune trace rejouable »* → **levée** : `domain_events` **est** la trace ; l'event ne
  fait que déclencher l'écriture.
- *« interdits par `laravel:no-observers` »* → **faux** : la convention vise les Observer,
  pas les events+listeners.
- *« ratent les updates de masse / `DB::table` »* → **tient** : seul vrai angle mort,
  rattrapé par le **reconcile** (3.6). À acter comme limite : un `Model::where()->update()`
  de masse ou un `$model->save()` hors transaction n'est synchronisé qu'au prochain reconcile.

Mécanique :
- Table `domain_events` : `{sequence (bigIncrements, PK, ordre global monotone), id (uuid,
  = X-Event-Id), aggregate_type, aggregate_id, event_type, payload (json, état complet),
  tenant_scope (client_id|null), occurred_at, published_at}`.
- **Modèles synchronisés** = noyau identité/orga (`Site`, `Client`, plus tard `User`,
  `professions`). Ils implémentent l'interface **`SyncableAggregate`** et utilisent le trait
  **`SyncsToReplica`** (`syncAggregateType()`, `syncTenantScope()`, `toSyncPayload()` —
  défaut = `attributesToArray()`, qui respecte `$hidden`, donc jamais `password`).
  `Site::syncTenantScope()` = `client_id` ; `Client::syncTenantScope()` =
  sa propre clé.
- **Listeners synchrones** (`dailyapps/event-distribution`) délégant au
  **`DomainEventRecorder`** (seul écrivain sanctionné de l'outbox). Câblage via une **config
  déclarative** `dailyapps/event-distribution/config/sync.php` : une map `aggregates`
  (`'clients' => Client::class`, `'sites' => Site::class`, `'users' => User::class`) lue par
  l'accesseur **`SyncAggregates`**. Le provider d'`event-distribution` parcourt cette liste
  pour câbler `created/updated` → `RecordAggregateUpserted` et `deleted` →
  `RecordAggregateDeleted`. Cette même liste sert aussi de table de résolution
  « type d'agrégat → modèle » aux endpoints snapshot/checksum. Ajouter un agrégat à la sync
  = **une ligne** dans `sync.php` (l'ancien `SyncableRegistry` poussé au boot par chaque
  layer est supprimé). Conforme à `laravel:no-observers` (câblage dans un service provider,
  pas dans le boot d'un modèle).
- **Deux verbes génériques** : `<agg>.upserted` (created/updated — et donc aussi un
  *restore*, qui re-save → `updated` → upsert portant `deleted_at=null`) et `<agg>.deleted`
  (soft-delete). Le payload étant un **upsert d'état complet idempotent**, delete/restore
  n'exigent aucune logique delta côté enfant.
- **Événements sémantiques** (peu nombreux) restent portés par des **classes Action**
  explicites, là où l'enfant fait *plus* qu'un upsert : `subscription.revoked` → purge,
  éventuel `user.role_changed`. C'est l'unique reliquat de l'approche Action.
- Un **relais** (`RelayDomainEvents`, job Horizon auto-redispatché + heartbeat planifié
  `everyMinute`) lit les lignes `published_at IS NULL` **ordonnées par `sequence`**,
  dispatche les jobs de livraison et tamponne `published_at`. `sequence` = colonne
  vertébrale de l'ordre + idempotence.
- **Sémantique de `sequence`** : `sequence` est **globale monotone** (`bigIncrements`).
  L'enfant **ne tient aucun watermark ni plancher par agrégat** : la `sequence` voyage
  jusque dans la ligne réplica (`_sync_sequence`) et l'arbitrage se fait **par ligne, dans
  l'upsert conditionnel** (cf. 3.3). C'est l'unique mécanisme d'ordre et d'idempotence.

### 3.2 Routage — qui reçoit quoi
- `users.*` / `organizations.*` (clients, sites) : scopés à un `client_id` (une org) → un
  enfant ne reçoit que les orgs **auxquelles son `Application` est souscrite**.
- `professions.*` : données de référence **globales** (`tenant_scope = null`) → fan-out à
  toutes les `Application` ayant activé la sync.
- (Le **catalogue applicatif n'émet pas** d'événements de sync : hors périmètre.)

**La table de routage = le pivot de souscription, pas une table `sync_subscriptions` ad hoc.**
Le schéma actuel **ne possède aucun de ces liens** ; ils sont à créer :

1. **`functional/subscriptions`** (nouveau layer) : pivot M2M **`Client ↔ Application`**
   (`{client_id, application_id, active, timestamps}`) = « cette org utilise cette app ».
   C'est la **source de vérité du tenant scope** des livraisons. Concept métier à part
   entière (entitlements / facturation à venir) → son propre layer plutôt que le catalogue.
2. **`Application` 1:1 `oauth_client` + coordonnées de livraison** : on ajoute à
   `Application` (table sœur dédiée, pour isoler le secret) le lien vers son `oauth_client`,
   un `endpoint_url` webhook, un `secret` HMAC et un drapeau `sync_enabled`. L'enfant =
   `Application` + son client OAuth.

Résolution des destinataires d'un événement de `tenant_scope = client_id` :
`subscriptions.where(client_id).where(active)` → les `Application` ; pour chacune avec
`sync_enabled`, **un job de livraison** vers son `endpoint_url`/`secret`.
**Une livraison = un job par (événement, Application destinataire)** → retries/DLQ par
destinataire. (`tenant_scope = null` ⇒ toutes les `Application` `sync_enabled`.)

> **Sens de dépendance OSDD.** Le relais vit dans la couche *transport*
> `dailyapps/event-distribution` mais le routage lit une donnée *fonctionnelle* (`subscriptions`).
> Pour ne pas inverser la règle OSDD, `event-distribution` **expose une interface unique
> `SyncDirectory`** que `Functional\Subscriptions\Resolver\SyncDirectoryFromSubscriptions`
> **implémente et binde** au container. Cette interface fusionne les deux résolveurs des
> conceptions antérieures (`SubscriberResolver` + `SnapshotResolver`) et répond aux trois
> questions de routage :
> - `subscribersFor($aggregateType, ?$clientId)` — **push** : qui doit recevoir l'événement ?
> - `scopeFor($applicationId)` — **pull** : que peut lire un enfant authentifié, et avec
>   quel secret ? (renvoie un `SnapshotScope`)
> - `applicationFor($applicationId)` — **contrôle** : coordonnées de livraison d'une app
>   indépendamment de toute souscription (pour notifier un revoke à une app qui n'est
>   *plus* souscripteur, donc hors du routage normal).
>
> Le transport ne dépend que de cette interface (inversion de dépendance) ; aucune
> connaissance métier ne fuit dans la couche de transport.

### 3.3 Livraison — webhook signé HMAC (canal recommandé)
- Job `DeliverDomainEvent` (`ShouldQueue`) sur une **file `sync` dédiée** (supervisor
  Horizon séparé de `default`, pour qu'un enfant lent n'affame pas les jobs d'auth).
- POST vers `endpoint_url` ; en-têtes `X-Event-Id`, `X-Event-Sequence`, `X-Aggregate`,
  `X-Signature` (HMAC-SHA256 sur le corps brut).
- **At-least-once + idempotence + ordre = UNE règle par ligne.** Pas de table de dédup, pas
  de watermark, pas de plancher par agrégat. L'enfant stampe sur chaque ligne réplica
  `_sync_sequence` (la `sequence` de l'événement) et `_sync_tenant` (le `tenant_scope`), puis
  son `ReplicaWriter` fait un **upsert conditionnel atomique** : la ligne n'est écrite que si
  `sequence` entrant **>** `_sync_sequence` stocké. La comparaison est dans **une seule
  instruction SQL** MySQL (`on duplicate key update … = if(values(_sync_sequence) >
  _sync_sequence, values(…), …)` — tout l'écosystème est MySQL), donc deux livraisons
  concurrentes (at-least-once) ne peuvent pas se marcher dessus.
  Un rejeu porte une `sequence` égale (no-op), un événement désordonné une `sequence` plus
  basse (ignoré). Comme **chaque payload est un upsert d'état complet, clé UUID** (pas un
  delta), c'est suffisant.
- **Retries + DLQ** : `tries` + backoff exponentiel Horizon ; échec final → `failed_jobs`
  visible sur le dashboard Horizon, rejouable en masse.

### 3.4 Amorçage / catch-up et cycle de vie des souscriptions
**Un seul mécanisme de pull** : l'endpoint sync dédié authentifié HMAC `GET /api/sync/snapshot`
(`SyncSnapshot`, dans `event-distribution`), paginé par curseur, **scopé via `scopeFor()`**
à l'ensemble des `client_id` lisibles par l'`Application`. Accepte un paramètre optionnel
`?tenant=<client_id>` (validé contre le scope de l'appelant — 403 si hors scope) pour
restreindre le snapshot à un seul tenant (utilisé par `PullTenant` sur grant). Pas de second
endpoint `watermark` : **chaque ligne de snapshot porte déjà son `_sync_sequence`** (la
`sequence` courante de l'outbox au moment du snapshot), qui devient le plancher de cette
ligne. Une fois l'enfant amorcé, le flux live applique tout événement de `sequence` plus
haute et ignore les rejeux — la course snapshot/stream se ferme par la règle par ligne du
3.3, sans watermark global. Ce même pull sert pour **trois usages** : amorçage initial
(`sync:bootstrap`), reconcile (`sync:reconcile`, cf. 3.6) et pull ciblé sur grant
(`PullTenant`).

Le pivot `Client ↔ Application` étant **explicite**, les transitions de souscription sont des
opérations de première classe :
- **Org souscrit à une app** → `PullOnGrant` émet un event de contrôle `subscription.granted`
  vers l'enfant → `HandleDomainEvent` dispatche `PullTenant` → pull ciblé
  `GET /api/sync/snapshot?tenant=<client_id>` (sans `since`, sans tombstone). Symétrique du
  revoke/`PurgeOnRevoke`.
- **Org se désabonne** → un **unique event de contrôle** `subscription.revoked`
  (`Functional\Subscriptions\Listeners\PurgeOnRevoke` → `DeliverDomainEvent` vers
  `applicationFor()`, qui reste joignable hors souscription) → l'enfant
  (`HandleDomainEvent::purge()`) **tombstone toutes les lignes de cette org** via la colonne
  générique `_sync_tenant` (jamais les autres), table par table.
- Conséquence : **« un user change d'org » se gère par le routage**, sans cas particulier.

### 3.5 SoftDeletes
Un delete n'est pas un « supprime la ligne » : c'est un événement `*.deleted` portant
`deleted_at`. L'enfant applique un soft-delete local (FK préservées : un `Site`
soft-deleted satisfait encore `users.site_id`). Une **restauration ressort comme un
`*.upserted` portant `deleted_at=null`** (le restore re-save le modèle → event `updated`),
donc pas de verbe `*.restored` distinct. ✅ respecte `laravel:no-cascade-delete` : la
cascade est exprimée en événements explicites par agrégat, jamais en hard-delete côté enfant.

### 3.6 Reconcile — filet de sécurité « événements ratés »
Commande planifiée côté enfant `sync:reconcile` (`ReconcileFromMother` ; delta horaire,
`--full` nocturne avec tombstones) qui re-pull le snapshot et corrige la dérive via le même
`ReplicaWriter` (l'upsert conditionnel rend l'opération sûre et idempotente). La mère expose
`GET /api/sync/checksum` (`SyncChecksum` : empreinte cheap count + last_updated_at sur le
périmètre souscrit) pour court-circuiter quand rien n'a bougé. → Le push donne la fraîcheur ;
le reconcile **garantit la correction** même en cas de perte de webhook.

---

## Décision 4 — Réplica protégé colonne par colonne (pas un read-only total)

Un modèle réplica enfant n'est **pas** forcément en lecture seule : il combine souvent les
colonnes du noyau répliqué (écrites par la sync) **et des colonnes locales propres à l'enfant**
(ex. un `User` enfant avec des champs supplémentaires), qui doivent rester librement
modifiables. La garde est donc **par colonne**, pas globale :
- **Trait `ReadOnlyReplica`** (`Dailyapps\PortalSync\Concerns`) optionnel sur un modèle réplica.
  Le modèle déclare ses colonnes possédées par la mère via `syncedColumns(): array` ; la garde
  ne lève `ReplicaIsReadOnlyException` que si une **colonne synchronisée** est *dirty* au `save`
  (les changements qui ne touchent que des colonnes locales passent), et refuse `delete`/
  `restore` (le cycle de vie appartient à la sync). (Le nom `ReadOnly` est interdit : `readonly`
  est un mot-clé réservé insensible à la casse.)
- La garde ne voit **jamais** l'ingestion : la sync écrit via `ReplicaWriter`
  (`DB::statement(...)` upsert conditionnel sur `_sync_sequence`), qui passe par `DB::table`,
  **pas par le modèle**, et n'écrit que les colonnes du payload → les colonnes locales sont
  préservées. La garde ne protège donc que contre l'édition *manuelle* (via le modèle) des
  colonnes mère par le code enfant.
- Durcissement optionnel : l'utilisateur DB applicatif de l'enfant en **SELECT-only** sur les
  colonnes/tables purement répliquées, un credential séparé restreint pour le worker de sync.
- Les modèles réplica n'exposent **ni `HasControl`, ni policies, ni contrôleur REST d'écriture**
  pour les colonnes mère → aucune surface d'écriture publique sur le noyau répliqué.

---

## Décision 5 — Placement dans le layering OSDD

- **`dailyapps/event-distribution`** (nouveau layer dans le dossier `dailyapps/`, *colonne
  de transport* ; pas `technical/` car c'est une architecture propre à l'écosystème
  dailyapps — pipeline outbox→webhook mère↔enfants — et non une capacité technique générique
  réutilisable dans n'importe quel projet) : **table outbox
  `domain_events`** + son modèle `DomainEventRecord` (côté mère) ; les **primitives de
  capture** génériques — interface `SyncableAggregate`, trait `SyncsToReplica`, listeners
  `RecordAggregateUpserted`/`RecordAggregateDeleted`, écrivain `DomainEventRecorder` ; la map
  `config/sync.php` (`aggregates`) lue par `SyncAggregates` (remplace l'ancien
  `SyncableRegistry` runtime) ; le relais `RelayDomainEvents` ; le job `DeliverDomainEvent` ;
  le signeur `HmacSigner` ; la file Horizon `sync` ; les endpoints `SyncSnapshot`/`SyncChecksum`
  ; **l'interface unique `SyncDirectory`** (bindée par `functional/subscriptions`) ; et le
  **test de contrat de payload** `PayloadContractTest`. Dépend de `technical/horizon` +
  `technical/osdd`. **Aucune sémantique métier, aucune table de souscription** ; les primitives
  ne référencent **aucun modèle de la mère** (type `Model&SyncableAggregate`).
- **`functional/subscriptions`** (layer fonctionnel) : pivot M2M `Client ↔ Application` + son
  modèle/ressource REST + l'implémentation `SyncDirectoryFromSubscriptions` de `SyncDirectory` ;
  lie directement, dans son provider, `PullOnGrant` sur l'event Eloquent `created` et
  `PurgeOnRevoke` sur `deleted` du modèle `Subscription` (plus de classes d'event
  `SubscriptionGranted`/`SubscriptionRevoked` ni de `$dispatchesEvents` : liaison directe aux
  events du modèle). `PullOnGrant` pousse l'event de contrôle `subscription.granted` →
  l'enfant lance `PullTenant` (pull ciblé `?tenant=<client_id>`, sans `since`) ;
  `PurgeOnRevoke` pousse `subscription.revoked`. Porte aussi les coordonnées de livraison de
  l'`Application` (`ApplicationSyncEndpoint` : `endpoint_url`, `secret` `#[Hidden]`,
  `sync_enabled`) — table sœur dédiée pour isoler le secret.
- **Capture par layer fonctionnel** : les modèles syncables (`Client`, `Site`, `User`)
  portent le trait `SyncsToReplica` et implémentent `SyncableAggregate` (avec leur
  `syncTenantScope()` : `Client` = sa clé ; `Site` = `client_id` ; `User` = via
  `user → site → client`). Ils sont **déclarés dans `config/sync.php`** ; aucun layer ne pousse
  plus dans un registry au boot. Pas d'émission depuis `functional/applications` (catalogue
  hors périmètre). Edges acycliques : les layers fonctionnels dépendent de
  `dailyapps/event-distribution`, comme ils dépendent déjà d'`access-control`.
- **`dailyapps/portal-sync-client`** (namespace `Dailyapps\PortalSync`) : le runtime
  **d'ingestion seule** installé chez les enfants — **aucun DDL, aucune macro, aucun modèle
  partagé**. Contient le contrôleur entrant `HandleDomainEvent` (vérifie HMAC → 401, fenêtre
  de rejeu sur `occurred_at`, applique l'upsert d'état complet **ou** purge un tenant
  révoqué), l'écrivain `ReplicaWriter` (upsert conditionnel sur `_sync_sequence`, ignore les
  colonnes inconnues), `MotherSyncClient` (pull HMAC), les commandes `sync:bootstrap` /
  `sync:reconcile`, et le trait `ReadOnlyReplica` + `ReplicaIsReadOnlyException`. L'enfant
  écrit lui-même ses migrations réplica (colonnes `_sync_sequence`/`_sync_tenant` requises).
- **Contrat d'événement** : l'enveloppe `EventEnvelope` (+ `SCHEMA_VERSION`) vit dans
  `dailyapps/event-distribution`. Le **contrat de forme** n'est plus une constante mais le
  payload lui-même, figé par `PayloadContractTest` (Décision 2).

---

## Sécurité (synthèse)

- **Canal webhook signé HMAC** retenu plutôt que Redis/broadcast partagé (couplage infra +
  fuite cross-tenant) ou pull permanent. Le pull est lui aussi un **endpoint sync dédié signé
  HMAC** (`/api/sync/snapshot`, `/api/sync/checksum`) ; il ne sert qu'à l'amorçage/reconcile/
  catch-up, avec le **même secret par `Application`** (aucune identité machine séparée).
- **AuthN** : un secret de signature **par `Application`** (coordonnées de livraison) ;
  `X-Signature = HMAC(secret, corps)`, rejet sur mismatch (401) + fenêtre de rejeu
  (`occurred_at`). Pas de dédup applicative : l'idempotence est portée par l'upsert
  conditionnel `_sync_sequence` (rejouer un event est un no-op).
- **AuthZ / isolation tenant** : filtrage par `tenant_scope` **au relais** via `subscribersFor()`
  (le job de livraison n'est même pas créé pour une `Application` non souscrite à l'org), pas
  seulement chez l'enfant — défense en profondeur. Le pull est borné par `scopeFor()` à
  l'**ensemble des `client_id` lisibles** par l'enfant.
- **Minimisation du payload** : seuls les champs nécessaires ; **jamais** `password`
  (déjà `#[Hidden]` sur `User`, et vérifié par `PayloadContractTest` +
  `SyncPayloadSecrecyTest`).
- **Rotation de secret** : secrets versionnés (`secret_id` dans l'en-tête) → signer avec le
  nouveau pendant que l'enfant accepte l'ancien sur une fenêtre de recouvrement. Telescope/
  Horizon doivent masquer signatures et secrets.

---

## Fichiers concernés (références)

- `composer.json` (racine) — wiring path-repo `./dailyapps/*` ; require `event-distribution`
  + `portal-sync-client` (le `portal-shared-schema` retiré).
- `dailyapps/event-distribution/config/sync.php` — map `aggregates` (capture + résolution),
  lue par `SyncAggregates`.
- `dailyapps/event-distribution/src/Contracts/SyncDirectory.php` — interface de routage unique
  (push/pull/contrôle), implémentée par `functional/subscriptions`.
- `dailyapps/event-distribution/tests/Feature/PayloadContractTest.php` — **le contrat de
  payload** (remplace le snapshot de dérive de schéma).
- `functional/{organizations,users}/database/migrations/*` — migrations mère **auto-portées**
  (`create_clients_table`, `create_sites_table`, `create_users_table` ; colonnes inline).
- `functional/subscriptions/src/Resolver/SyncDirectoryFromSubscriptions.php` +
  `Listeners/PurgeOnRevoke.php` + `Models/ApplicationSyncEndpoint.php` — résolution, revoke,
  coordonnées de livraison.
- `dailyapps/portal-sync-client/src/` — runtime enfant : `Http/Controllers/HandleDomainEvent`,
  `Ingestion/ReplicaWriter`, `Support/MotherSyncClient`, `Console/Commands/{BootstrapReplica,
  ReconcileFromMother}`, `Concerns/ReadOnlyReplica`.
- `technical/horizon/config/horizon.php` — file/supervisor `sync`, retries, backoff de
  `DeliverDomainEvent`.
- `technical/osdd/database/migrations/0000_00_00_000003_create_job_batches_table.php` —
  emplacement sœur pour `domain_events` (mère). Les tables réplica (avec
  `_sync_sequence`/`_sync_tenant`) vivent **côté enfant**, dans ses propres migrations.
- [`technical/ARCHITECTURE-SSO.md`](./ARCHITECTURE-SSO.md) — la règle d'appartenance des
  migrations que la stratégie réplica respecte/étend.

---

## Plan d'implémentation incrémental

Ordre recommandé (chaque étape est validable indépendamment) :

1. **[FAIT, puis simplifié 2026-06-20]** **Anti-duplication** : la version initiale créait
   `dailyapps/portal-shared-schema` (macros + snapshot de dérive). **Remplacé** : plus de
   package de schéma, migrations mère **auto-portées**, le contrat est le payload JSON figé
   par `PayloadContractTest` (cf. Décision 2). ✅ critère actuel : migrations mère vertes +
   `PayloadContractTest` vert.
2. **[FAIT]** **Souscription + routage** : `functional/subscriptions` (table `subscriptions`
   `Client ↔ Application` + `licenses`, modèle, ressource/contrôle/policy REST lomkit isolés
   par tenant via `ClientPerimeter` ; révoquer = supprimer la ligne, pas de flag `active`) + mini `dailyapps/event-distribution` portant l'interface
   `SubscriberResolver` + value `Subscriber`, implémentée et bindée par `subscriptions`.
   ✅ critère atteint : `resolve('users', $clientId)` rend les `Application` souscrites + actives.
   **Différé à plus tard** : les **coordonnées de livraison** de l'`Application` (lien
   `oauth_client` 1:1, `endpoint_url`, secret HMAC, `sync_enabled`) → incrément livraison ;
   `Subscriber` ne porte donc encore que `applicationId`. Les **réactions de cycle de vie**
   (grant/revoke) sont différées (aucun consommateur avant l'outbox) ; elles ont finalement
   atterri en liaisons directes aux events Eloquent `created`/`deleted` du modèle
   `Subscription` (`PullOnGrant`/`PurgeOnRevoke`), sans classe d'event dédiée.
3. **[FAIT, simplifié 2026-06-20]** **Outbox + capture par events Eloquent** :
   `dailyapps/event-distribution` — table `domain_events` + `DomainEventRecord`, interface
   `SyncableAggregate`, trait `SyncsToReplica`, listeners `RecordAggregateUpserted`/
   `RecordAggregateDeleted`, écrivain `DomainEventRecorder`. Le câblage runtime
   `SyncableRegistry` est **remplacé** par la map déclarative `config/sync.php` lue par
   `SyncAggregates`. ✅ critère : un create/update/soft-delete sur un agrégat écrit la ligne
   `domain_events` attendue (verbe, `tenant_scope`, payload d'état complet), `sequence`
   croissante, et un `DB::transaction` qui rollback **n'écrit aucune ligne** (atomicité).
4. **[FAIT]** **1 livraison signée → 1 enfant jouet** : côté mère —
   `EventEnvelope` (contrat versionné `SCHEMA_VERSION`), `HmacSigner`, job `RelayDomainEvents`
   (auto-redispatché, draine l'outbox par `sequence`, route via `SubscriberResolver`, tamponne
   `published_at`, heartbeat planifié `everyMinute`), job `DeliverDomainEvent` (file `sync`,
   `tries`/backoff, POST signé `X-Signature`=HMAC-SHA256 du corps brut + en-têtes `X-Event-Id`/
   `X-Event-Sequence`/`X-Aggregate`/`X-Schema-Version`), supervisor Horizon `sync` dédié. Les
   **coordonnées de livraison** différées à l'incr. 2 sont posées : table sœur
   `application_sync_endpoints` (`endpoint_url`, `secret` `#[Hidden]`, `sync_enabled`) +
   `ApplicationSyncEndpoint`, et la résolution ne rend que les souscripteurs `sync_enabled`.
   Côté enfant (désormais **`dailyapps/portal-sync-client`**, simplifié 2026-06-20) — trait
   `ReadOnlyReplica` (le nom `ReadOnly` est interdit : mot-clé réservé) +
   `ReplicaIsReadOnlyException`, écrivain `ReplicaWriter` (**upsert conditionnel
   `_sync_sequence`**, colonnes inconnues ignorées) et contrôleur `HandleDomainEvent` (vérif
   HMAC → 401 sinon, fenêtre de rejeu, upsert d'état complet **ou** purge `_sync_tenant`).
   La table `processed_events`/`ProcessedEvent` est **supprimée** (dédup remplacée par la
   règle par ligne). ✅ critère : un event signé est appliqué ; **rejouer le même event est un
   no-op** ; mauvaise signature rejetée (401) ; relais ne dispatche que vers les `Application`
   `sync_enabled` et tamponne `published_at`.
5. **[FAIT, simplifié 2026-06-20]** **Amorçage + cycle de souscription + reconcile** :
   - **Amorçage** : l'enfant **pull** un snapshot scopé via l'endpoint sync dédié signé HMAC
     `GET /api/sync/snapshot` (commande `sync:bootstrap`). Chaque ligne porte son
     `_sync_sequence` → plancher par ligne. Plus d'endpoint `/api/sync/watermark` ni de
     plancher par agrégat.
   - **Cycle de souscription** : un **grant** émet l'event de contrôle `subscription.granted`
     (`PullOnGrant`) → l'enfant dispatche `PullTenant` (pull ciblé
     `GET /api/sync/snapshot?tenant=<client_id>`, sans `since`, sans tombstone) — symétrique
     du revoke. Un **revoke** émet **un** event de contrôle `subscription.revoked`
     (`PurgeOnRevoke`) → l'enfant purge par `_sync_tenant`.
   - **Reconcile** : `GET /api/sync/checksum` (empreinte cheap count + last_updated_at) +
     `sync:reconcile` (delta horaire / `--full` nocturne) — re-pull via le même `ReplicaWriter`.
6. **[FAIT]** **Sécurité** : test négatif (mauvaise signature → 401), test d'isolation
   (`SyncPayloadSecrecyTest`, scope par souscription), vérification que les champs `#[Hidden]`
   n'apparaissent jamais dans un payload (`PayloadContractTest`).

> **Simplification du 2026-06-20 (synthèse)** : suppression du package de schéma partagé
> `dailyapps/portal-shared-schema` (macros, modèles abstraits, snapshot de dérive, SemVer
> expand/contract) ; idempotence+ordre ramenés à une règle par ligne (`_sync_sequence` upsert
> conditionnel + `_sync_tenant` purge) en lieu et place de `processed_events`/
> `replica_sync_state`/plancher par agrégat ; deux chemins de données (push + un seul pull
> snapshot) au lieu de trois (backfill push retiré) ; `SyncableRegistry` → `config/sync.php`
> (`SyncAggregates`) ; deux résolveurs → une interface `SyncDirectory` ; nouveau package
> d'ingestion seule `dailyapps/portal-sync-client` (namespace `Dailyapps\PortalSync`).

> Conventions à respecter pendant l'implémentation : `laravel:no-observers`,
> `laravel:no-fat-models`, `laravel:no-cascade-delete`, `laravel:softdeletes-require-prunable`,
> tests par layer via `php artisan osdd:phpunit`, exécution dans le conteneur
> (`make shell p=portal-api`).
