# Architecture — Synchronisation de données App mère → Apps enfants

> Document de décision d'architecture. **Conception validée, non encore implémentée.**
> Sujet central : faut-il un réplica local, et comment **arrêter de dupliquer les schémas
> de tables** entre la mère et les enfants. Complète [`ARCHITECTURE-SSO.md`](./ARCHITECTURE-SSO.md).
>
> **Révisé le 2026-06-16** (confronté au code) : « enfant » = `Application` (1:1
> `oauth_client`) ; routage tenant via un M2M explicite `Client ↔ Application` dans un
> nouveau layer `functional/subscriptions` (remplace l'ancien lien `oauth_clients↔client_id`
> et la table `sync_subscriptions`) ; `processed_events` côté enfant ; interface
> `SubscriberResolver` pour garder le transport pur (inversion de dépendance).
> Le package partagé devient `dailyapps/portal-shared-schema` (namespace
> `Dailyapps\PortalShared`), hébergé dans un **3ᵉ dossier de layers `dailyapps/`** :
> il a fallu enregistrer ce chemin via `'dailyapps' => base_path('dailyapps')` dans
> `technical/osdd/config/osdd.php` (`layers.paths`) et un path-repo `./dailyapps/*`
> dans le `composer.json` racine.
>
> **Révisé le 2026-06-17** : la **capture** de l'outbox passe des « classes Action par
> mutation » aux **events de cycle de vie Eloquent + listeners synchrones** (interface
> `SyncableAggregate` + trait `SyncsToReplica`, listeners `RecordAggregate*`, écrivain
> `DomainEventRecorder`). Câblage via un **`SyncableRegistry`** (idiome
> `ControlRegistry`/`SeederRegistry`) : chaque layer pousse ses modèles depuis son provider,
> `event-distribution` les câble en `app->booted()`. Couvre le `/mutate` lomkit et
> l'Eloquent courant, conforme à `laravel:no-observers`, reconcile en filet pour les
> écritures de masse. Les Actions ne servent plus qu'aux **events sémantiques**. Détail en
> Décision 3.1.

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
  de colonnes différent → schéma de base partagé + colonnes locales par enfant.
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
> par le pipeline ci-dessous, et sa structure provient d'**un package partagé unique**
> (Décision 2), pas d'une recopie.

---

## Décision 2 — Comment ARRÊTER de dupliquer les schémas de tables

C'est le cœur du problème. Le modèle OSDD est ici un atout : **un layer est déjà un
package composer**. On promeut donc le schéma partagé en **package OSDD publié** que la
mère **et** chaque enfant `composer require`. La définition des tables vit **une seule
fois**.

### Le mécanisme : une seule source de vérité pour les colonnes

On ne partage **pas** les "gros" modèles de la mère (ils traînent Passport, Fortify,
spatie, lomkit, media, Astrotomic — un enfant ne doit pas booter tout ça pour lire une
ligne `users`). On partage **la forme**, via une **macro de Blueprint** + des
**modèles abstraits en lecture seule**.

Que contient le package partagé `dailyapps/portal-shared-schema` (namespace
`Dailyapps\PortalShared\`) — **petit, périmètre noyau uniquement** :
- **Macros de Blueprint** — une méthode par table du noyau qui pose les **colonnes
  canoniques de base** : `Blueprint::portalUsers()`, `portalClients()`, `portalSites()`,
  `portalProfessions()`. **C'est l'unique source de vérité des colonnes communes.**
  → Le besoin « variable selon l'enfant » est précisément ce qui justifie la macro :
  chaque enfant appelle `Schema::create('clients', fn ($t) => $t->portalClients())` puis
  **ajoute ses propres colonnes** dans la même migration locale.
  → La macro **retourne le Blueprint** pour permettre le chaînage. `timestamps()` est posé
  par la macro, mais **`softDeletes()` est opt-in** : le consommateur l'ajoute s'il le veut
  (`$t->portalClients()->softDeletes()`), tout comme ses colonnes locales.
  → Les FK ne sont **pas** posées par les macros de table : elles se **chaînent** via le
  helper `portalForeignId(<model>[, <colonne>, nullable: ...])` (idiome
  `foreignIdFor(...)->constrained()` sous le capot, retourne le Blueprint) — même style
  fluide que `softDeletes()`. Ajouter une FK = un appel chaîné de plus, jamais un nouvel
  argument de macro :
  `$t->portalUsers()->portalForeignId(Site::class)->portalForeignId(User::class, 'manager_id', nullable: true)->softDeletes()`.
  Le **modèle est passé par le consommateur** (`foreignIdFor` l'instancie, et il **diffère
  d'une app à l'autre** — la mère a `Functional\Organizations\Models\Site`, un enfant le
  sien), donc le package ne référence **aucun modèle de la mère**.
- **Migrations réplica opt-in** qui appellent ces macros — chargées **uniquement chez les
  enfants** (mode `replica`).
- **Modèles abstraits read-only** (`HasUuids`, table, casts, **relations** du noyau :
  `user→site`, `site→client`, `user→manager`) avec un trait `ReadOnly` (cf. Décision 4).
  Les enfants peuvent les étendre pour ajouter relations/colonnes locales.
- Une **constante `SCHEMA_VERSION`** que le pipeline de sync lit pour négocier la
  compatibilité de contrat.

> Pas de catalogue applicatif ici (hors périmètre), donc **pas d'Astrotomic, pas d'enums
> de rôle/app** à partager → package volontairement minuscule.

Ce qui reste **local à chaque enfant** : son service provider qui opte pour les
migrations réplica, ses éventuelles colonnes de projection supplémentaires (dans une
migration *à lui*, jamais en éditant le package), et ses consommateurs de sync.

Ce qui reste **local à la mère** : ses layers fonctionnels "gros" inchangés
(seul écrivain), mais **leurs migrations sont refactorées pour appeler les mêmes
macros**, puis ajouter les colonnes propres à la mère (`password`, `two_factor_*`).
→ Mère et enfants génèrent leurs tables depuis **la même macro** : elles **ne peuvent
plus diverger, par construction**.

> **Réalité du refactor (vérifiée dans le code)** : ce n'est pas un « 1 table = 1
> migration » propre. Le schéma `users` mère est **éclaté sur 2 migrations**
> (`…_create_users_table` + `…_add_email_verification_and_two_factor_to_users_table`) et
> la migration `create` **mélange déjà** colonnes noyau et colonnes mère-only (`password`,
> `email_verified_at` y sont). La macro `portalUsers()` pose **le noyau commun** ; la
> migration `create` mère appelle la macro **puis** rajoute `password`/`email_verified_at`,
> et la 2ᵉ migration ajoute les `two_factor_*`. À acter avant l'incrément 1 (ce n'est pas
> un copier-coller). `clients` (`id, name`) et `sites` sont en revanche triviaux.

### Règle d'appartenance des migrations (respect de ARCHITECTURE-SSO.md)

La règle maison « les colonnes suivent le layer propriétaire de leur table ; un layer
technique ne migre jamais la table d'un layer fonctionnel » est **étendue, pas violée** :
- La **vérité des colonnes** = la macro (une seule définition dans toute l'organisation).
- **La mère NE LANCE PAS les migrations du package** : ses layers fonctionnels gardent
  la propriété de leurs fichiers de migration (ils appellent juste la macro). ✅ règle
  respectée côté app autoritaire.
- **Les enfants LANCENT les migrations réplica du package** : chez eux il n'y a pas de
  layer fonctionnel concurrent, donc le package porte légitimement la migration réplica.
- Pas de collision de table (`users`, `clients`…) : **bases de données différentes**
  (DB mère vs DB de chaque enfant). Le fichier de migration n'est jamais exécuté dans
  les deux ; seule la macro est commune.
- Garde anti-dérive en CI : un test construit chaque table depuis sa macro vers SQLite
  et compare à un snapshot de schéma versionné, **côté mère ET côté package**.

### Comment distribuer ce package (monorepo path-repo aujourd'hui) — version légère

Pour 3-4 tables et une poignée d'enfants, **inutile de monter Packagist privé / satis /
split CI** dès maintenant. On reste simple, quitte à durcir plus tard.

| Option | Coût (petite équipe) | SemVer | Verdict pour CE périmètre |
|---|---|---|---|
| **(i) Repo git dédié + entrée `repositories` type `vcs` chez chaque enfant** | Faible | Natif (tags) | **✅ Recommandé maintenant** |
| (ii) Packagist privé / satis + miroir split CI | Moyen | Natif | ⏳ Plus tard, quand ≥ 3-4 enfants ou release fréquentes |
| (iii) git submodule/subtree comme conso | Pénible | Aucun | ❌ |

Flux recommandé :
1. On **développe le package dans le monorepo mère**, dans le **nouveau dossier de layers
   dédié `dailyapps/`** (`dailyapps/portal-shared-schema/`, capté par le glob path-repo
   `./dailyapps/*` → friction nulle côté mère).
2. On le **pousse aussi vers un repo git dédié** (subtree push sur tag, ou simple repo
   séparé si on accepte de le développer là). Chaque enfant ajoute une entrée
   `repositories: [{type: vcs, url: …}]` et `composer require dailyapps/portal-shared-schema:^1.x`.
3. **Le monorepo mère reste la source de vérité.** On migrera vers (ii) sans douleur le
   jour où le nombre d'enfants/releases le justifie (le `composer require` ne change pas).

### Versioning (le contrat de schéma = l'API publique)
- **MAJOR** = rupture de contrat (colonne supprimée/renommée sans transition).
- **MINOR** = additif (nouvelle colonne *nullable*, nouvelle table/relation).
- **PATCH** = non-schéma.
- Discipline **expand/contract** obligatoire : on *ajoute en nullable* (MINOR), la sync
  remplit, on *retire* seulement plus tard (MAJOR) quand tous les enfants ont migré. Un
  renommage = ajouter+double-écrire+supprimer, jamais en place.

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
  défaut = `attributesToArray()`, qui respecte `$hidden`, donc jamais `password`/
  `two_factor_*`). `Site::syncTenantScope()` = `client_id` ; `Client::syncTenantScope()` =
  sa propre clé.
- **Listeners synchrones** (`dailyapps/event-distribution`) délégant au
  **`DomainEventRecorder`** (seul écrivain sanctionné de l'outbox). Câblage via un
  **`SyncableRegistry`** (idiome `ControlRegistry`/`SeederRegistry` du repo, bindé
  singleton) : chaque layer **pousse ses modèles syncables** depuis son provider
  (`$registry->push(Client::class, Site::class)`), et le provider d'`event-distribution`
  consomme la liste dans `app->booted()` (après que tous les layers ont poussé) pour câbler
  `created/updated` → `RecordAggregateUpserted` et `deleted` → `RecordAggregateDeleted`.
  Conforme à `laravel:no-observers` (câblage effectif dans un service provider, pas dans le
  boot d'un modèle) et cohérent avec l'existant.
- **Deux verbes génériques** : `<agg>.upserted` (created/updated — et donc aussi un
  *restore*, qui re-save → `updated` → upsert portant `deleted_at=null`) et `<agg>.deleted`
  (soft-delete). Le payload étant un **upsert d'état complet idempotent**, delete/restore
  n'exigent aucune logique delta côté enfant.
- **Événements sémantiques** (peu nombreux) restent portés par des **classes Action**
  explicites, là où l'enfant fait *plus* qu'un upsert : `subscription.revoked` → purge,
  éventuel `user.role_changed`. C'est l'unique reliquat de l'approche Action.
- Un **relais** (job Horizon planifié, incrément 4) lit les lignes `published_at IS NULL`
  **ordonnées par `sequence`**, dispatche les jobs de livraison et tamponne `published_at`.
  `sequence` = colonne vertébrale de l'ordre + idempotence.
- **Sémantique de `sequence` (à figer)** : `sequence` est **globale monotone** (un seul
  compteur, ex. `bigIncrements`). Deux usages distincts à ne pas confondre :
  - **amorçage** : l'enfant retient **un watermark global unique** (`sequence` haute du
    snapshot) ;
  - **régime permanent** : l'enfant garde `last_applied_sequence` **par agrégat** et ignore
    tout événement `≤` celui-ci pour cet agrégat. Compatible avec le watermark global
    d'amorçage (cf. 3.3).

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
> Pour ne pas inverser la règle OSDD, `event-distribution` **expose une interface
> `SubscriberResolver`** (`resolve(string $aggregateType, ?string $clientId): iterable<Subscriber>`)
> que `functional/subscriptions` **implémente et binde** au container. Le transport ne
> dépend que de l'interface (inversion de dépendance) ; aucune connaissance métier ne fuit
> dans la couche de transport.

### 3.3 Livraison — webhook signé HMAC (canal recommandé)
- Job `DeliverDomainEvent` (`ShouldQueue`) sur une **file `sync` dédiée** (supervisor
  Horizon séparé de `default`, pour qu'un enfant lent n'affame pas les jobs d'auth).
- POST vers `endpoint_url` ; en-têtes `X-Event-Id`, `X-Event-Sequence`, `X-Aggregate`,
  `X-Signature` (HMAC-SHA256 sur le corps brut).
- **At-least-once + idempotence** : l'enfant déduplique sur `X-Event-Id` (table
  `processed_events`) et applique dans une transaction locale. Comme **chaque payload est
  un upsert d'état courant complet, clé UUID** (pas un patch delta), le rejeu est
  naturellement idempotent.
- **Ordre/versioning** : l'enfant garde `last_applied_sequence` par agrégat et **ignore
  tout événement de `sequence` ≤ dernier appliqué** (livraison désordonnée/obsolète sans
  danger). Le payload porte un `schema_version` ; major inconnu ⇒ l'enfant quarantaine et
  bascule sur reconcile (signal explicite « enfant à mettre à jour »).
- **Retries + DLQ** : `tries` + backoff exponentiel Horizon ; échec final → `failed_jobs`
  visible sur le dashboard Horizon, rejouable en masse.

### 3.4 Amorçage / backfill et cycle de vie des souscriptions
Réutilise les **ressources REST lomkit existantes** (`UserResource`, `SiteResource`…)
comme API snapshot, **scopée à l'ensemble des `client_id` souscrits par l'`Application`**
(pas un seul — un enfant sert N orgs) : l'enfant pagine l'état initial et note la
**`sequence` haute** retournée, puis s'abonne au flux live et **ignore tout événement de
`sequence` ≤ haute** → ferme proprement la course snapshot/stream.

Le pivot `Client ↔ Application` étant **explicite**, les transitions de souscription
deviennent des opérations de première classe (impossibles tant que l'appartenance n'était
que dérivée des rôles) :
- **Org souscrit à une app** (`subscriptions` +1 ligne) → **backfill ciblé de cette org
  uniquement** vers cet enfant (snapshot scopé au nouveau `client_id`).
- **Org se désabonne** (`active=false`) → événement `subscription.revoked` → l'enfant
  **purge / tombstone les données de cette org** dans son réplica (jamais les autres).
- Conséquence : **« un user change d'org » se gère par le routage**, sans cas particulier.
  Si l'app sert l'ancienne *et* la nouvelle org → simple upsert ; si elle ne sert que
  l'ancienne → tombstone. Le diff des ensembles de souscripteurs porte toute la logique.

### 3.5 SoftDeletes
Un delete n'est pas un « supprime la ligne » : c'est un événement `*.deleted` portant
`deleted_at`. L'enfant applique un soft-delete local (FK préservées : un `Site`
soft-deleted satisfait encore `users.site_id`). Une **restauration ressort comme un
`*.upserted` portant `deleted_at=null`** (le restore re-save le modèle → event `updated`),
donc pas de verbe `*.restored` distinct. ✅ respecte `laravel:no-cascade-delete` : la
cascade est exprimée en événements explicites par agrégat, jamais en hard-delete côté enfant.

### 3.6 Reconcile — filet de sécurité « événements ratés »
Job planifié côté enfant `ReconcileFromMother` (delta horaire `updated_at >= last`,
full nocturne) qui compare au réplica et corrige la dérive (upsert + tombstones). La mère
expose un endpoint `checksum`/`count-by-tenant` léger pour court-circuiter quand rien n'a
bougé. → Le push donne la fraîcheur ; le reconcile **garantit la correction** même en cas
de perte de webhook.

---

## Décision 4 — Réplica strictement en lecture seule

Défense en profondeur, dans les modèles partagés, pour qu'un dev enfant ne puisse pas
muter le réplica par accident :
- **Trait `ReadOnly`** sur chaque modèle réplica : garde sur `saving/creating/updating/
  deleting/restoring` qui lève `ReplicaIsReadOnlyException` ; `save()/update()/delete()/
  forceDelete()/insert()/restore()` surchargés pour lever immédiatement ; `$fillable`
  vide.
- Le **seul écrivain sanctionné** = la couche d'ingestion sync, qui écrit via un séminaire
  dédié `withoutReplicaGuard()` / `DB::table()->upsert()` → toute l'écriture réplica est
  auditable en un seul endroit.
- Durcissement optionnel : l'utilisateur DB applicatif de l'enfant en **SELECT-only** sur
  les tables réplica, un credential séparé restreint pour le worker de sync.
- Les modèles réplica n'ont **ni `HasControl`, ni policies, ni contrôleur REST d'écriture**
  → aucune surface d'écriture exposée.

---

## Décision 5 — Placement dans le layering OSDD

- **`dailyapps/event-distribution`** (nouveau layer dans le dossier `dailyapps/`, *colonne
  de transport* ; pas `technical/` car c'est une architecture propre à l'écosystème
  dailyapps — pipeline outbox→webhook mère↔enfants — et non une capacité technique générique
  réutilisable dans n'importe quel projet) : **table outbox
  `domain_events`** + son modèle `DomainEventRecord` (côté mère) ; les **primitives de
  capture** génériques — interface `SyncableAggregate`, trait `SyncsToReplica`, listeners
  `RecordAggregateUpserted`/`RecordAggregateDeleted`, écrivain `DomainEventRecorder`,
  `SyncableRegistry` (consommé en `app->booted()`) ; le relais ; le job `DeliverDomainEvent` ; le signeur HMAC ; la file Horizon `sync` ;
  **l'interface `SubscriberResolver`** (bindée par `functional/subscriptions`). Dépend de
  `technical/horizon` + `technical/osdd`. **Aucune sémantique métier, aucune table de
  souscription** (elle vit côté fonctionnel) ni `processed_events` (elle vit côté enfant) ;
  les primitives ne référencent **aucun modèle de la mère** (type `Model&SyncableAggregate`).
- **`functional/subscriptions`** (nouveau layer fonctionnel) : pivot M2M
  `Client ↔ Application` + son modèle/ressource REST + l'implémentation de
  `SubscriberResolver` ; émet `SubscriptionGranted` / `SubscriptionRevoked` (déclenchent
  backfill ciblé / purge côté enfant). Porte aussi les coordonnées de livraison de
  l'`Application` (lien `oauth_client`, `endpoint_url`, secret, `sync_enabled`) — table
  sœur dédiée pour isoler le secret.
- **Capture + actions émettrices par layer fonctionnel** (seul le propriétaire câble ses
  modèles et connaît la sémantique des events spéciaux) :
  - `functional/organizations` : marque `Site`/`Client` `SyncableAggregate` via le trait
    `SyncsToReplica` (+ `syncTenantScope()` propre à chacun) et **les pousse dans le
    `SyncableRegistry`** depuis son provider (`registerSyncables()`) → `sites.upserted`/
    `.deleted`, `clients.upserted`/`.deleted`. **[FAIT]**
  - `functional/users` : idem pour `User` (`tenant_scope` via `user → site → client`) ; les
    changements à forte sémantique (`user.role_changed`) restent portés par une **Action**
    explicite (ex. `ChangeUserApplicationRole`), pas par la capture générique.
  - `functional/subscriptions` : `SubscriptionGranted` / `SubscriptionRevoked`.
  - (futur) `professions` : événements de référence globale (`tenant_scope = null`).
  - **Pas** d'émission de **réplication** depuis `functional/applications` (catalogue hors
    périmètre) — il ne fournit que l'entité `Application` au routage.
  - Edges acycliques : les layers fonctionnels dépendent de `dailyapps/event-distribution`
    (pour `RecordsDomainEvent` + l'enveloppe), comme ils dépendent déjà d'`access-control`.
- **`dailyapps/portal-shared-schema`** (package partagé, Décision 2 ; vit dans le dossier
  de layers `dailyapps/`) : le miroir installé chez
  les enfants — mêmes macros/modèles read-only + **table `processed_events` (dédup
  `X-Event-Id`, côté enfant)** + le contrôleur entrant `HandleDomainEvent` (vérifie HMAC,
  déduplique, applique upsert/soft-delete) + `ReconcileFromMother`.
- **Contrat d'événement versionné** : l'enveloppe + `schema_version` peuvent vivre dans
  `dailyapps/event-distribution` directement (périmètre petit → pas besoin d'un package
  `sync-contracts` séparé au départ).

---

## Sécurité (synthèse)

- **Canal webhook signé HMAC** retenu plutôt que Redis/broadcast partagé (couplage infra +
  fuite cross-tenant) ou pull permanent. Le pull authentifié REST ne sert qu'à
  l'amorçage/reconcile (réutilise l'auth Passport existante, aucun nouveau secret).
- **AuthN** : un secret de signature **par `Application`** (coordonnées de livraison) ;
  `X-Signature = HMAC(secret, corps)`, rejet sur mismatch + fenêtre de rejeu (`occurred_at`)
  + dédup `X-Event-Id`.
- **AuthZ / isolation tenant** : filtrage par `tenant_scope` **au relais** via le pivot
  `subscriptions` (le job de livraison n'est même pas créé pour une `Application` non
  souscrite à l'org), pas seulement chez l'enfant — défense en profondeur. Même filtre
  appliqué aux appels REST d'amorçage via les contrôles lomkit (`UserControl`, `SiteControl`,
  déjà `ClientPerimeter`) restreints à l'**ensemble des `client_id` souscrits** par l'enfant.
- **Minimisation du payload** : seuls les champs nécessaires ; **jamais** `password`,
  `two_factor_secret`, `two_factor_recovery_codes` (déjà `#[Hidden]` sur `User`).
- **Rotation de secret** : secrets versionnés (`secret_id` dans l'en-tête) → signer avec le
  nouveau pendant que l'enfant accepte l'ancien sur une fenêtre de recouvrement. Telescope/
  Horizon doivent masquer signatures et secrets.

---

## Fichiers concernés (références)

- `composer.json` (racine) — wiring path-repo + point d'insertion du publish du package.
- `vendor/xefi/laravel-osdd/src/LayerServiceProvider.php` — base étendue par les nouveaux
  providers (`loadMigrationsFrom`, `withRouting`, `overrideConfigFrom`).
- `functional/users/database/migrations/2026_04_26_193606_create_users_table.php` — DDL
  autoritaire à factoriser dans la macro `portalUsers()` ; montre les colonnes mère-only.
- `functional/users/src/Models/User.php` — le "gros" modèle dont les traits mère-only
  (Passport/Fortify/spatie/media/HasControl) doivent être **exclus** du modèle réplica.
- `technical/oauth-server/src/Actions/RegisterChildClient.php` — patron des classes Action ;
  point d'attache du lien **`Application` 1:1 `oauth_client`** (l'enfant a une identité OAuth).
- `functional/applications/src/Models/Application.php` + ses migrations — l'entité
  `Application` = identité du souscripteur ; reçoit le lien `oauth_client` + coordonnées de
  livraison (table sœur).
- `functional/organizations/src/Models/Client.php` — le `Client` (org/tenant), côté M2M du
  pivot de souscription.
- **`functional/subscriptions/`** (à créer) — pivot `Client ↔ Application`, modèle, ressource
  REST, implémentation de `SubscriberResolver`, événements `SubscriptionGranted/Revoked`.
- `technical/access-control/src/Access/Perimeters/ClientPerimeter.php` — le scope tenant
  **déjà existant** réutilisé pour borner l'amorçage REST aux orgs souscrites.
- `technical/horizon/config/horizon.php` — où déclarer la file/supervisor `sync`, retries,
  backoff de `DeliverDomainEvent`.
- `technical/osdd/database/migrations/0000_00_00_000003_create_job_batches_table.php` —
  convention/emplacement sœur pour `domain_events` (mère) ; `processed_events` vit côté
  enfant dans `dailyapps/portal-shared-schema` (dossier de layers `dailyapps/`).
- [`technical/ARCHITECTURE-SSO.md`](./ARCHITECTURE-SSO.md) — la règle d'appartenance des
  migrations que la stratégie réplica respecte/étend.

---

## Plan d'implémentation incrémental

Ordre recommandé (chaque étape est validable indépendamment) :

1. **[FAIT]** **Anti-duplication d'abord** : créer `dailyapps/portal-shared-schema` avec les macros
   reproduisant *exactement* les colonnes actuelles ; refactorer les migrations mère pour
   appeler les macros ; ajouter le **test snapshot de dérive** des deux côtés
   (`make shell p=portal-api` puis `php artisan test`). ✅ critère : migration mère verte +
   snapshot identique mère/package.
2. **[FAIT]** **Souscription + routage** : `functional/subscriptions` (table `subscriptions`
   `Client ↔ Application` + `licenses`, modèle, ressource/contrôle/policy REST lomkit isolés
   par tenant via `ClientPerimeter` ; révoquer = supprimer la ligne, pas de flag `active`) + mini `dailyapps/event-distribution` portant l'interface
   `SubscriberResolver` + value `Subscriber`, implémentée et bindée par `subscriptions`.
   ✅ critère atteint : `resolve('users', $clientId)` rend les `Application` souscrites + actives.
   **Différé à plus tard** : les **coordonnées de livraison** de l'`Application` (lien
   `oauth_client` 1:1, `endpoint_url`, secret HMAC, `sync_enabled`) → incrément livraison ;
   `Subscriber` ne porte donc encore que `applicationId`. Les **événements**
   `SubscriptionGranted`/`SubscriptionRevoked` sont différés (aucun consommateur avant
   l'outbox).
3. **[FAIT]** **Outbox + capture par events Eloquent** : `dailyapps/event-distribution` —
   table `domain_events` + `DomainEventRecord`, interface `SyncableAggregate`, trait
   `SyncsToReplica`, listeners `RecordAggregateUpserted`/`RecordAggregateDeleted`, écrivain
   `DomainEventRecorder`, `SyncableRegistry` (consommé en `app->booted()`) ; `Site`/`Client`
   marqués syncables et poussés dans le registry par `OrganizationsServiceProvider`. ✅ critère : un
   create/update/soft-delete sur `Site` écrit la ligne `domain_events` attendue (verbe,
   `tenant_scope`, payload d'état complet), `sequence` croissante, et un `DB::transaction`
   qui rollback **n'écrit aucune ligne** (atomicité — `cf. SiteSyncTest`). L'approche
   « 1 Action = 1 mutation » (`RenameSite`) est **abandonnée** au profit de la capture
   persistance ; les Actions ne restent que pour les **events sémantiques** (incr. ultérieurs).
4. **[FAIT]** **1 livraison signée → 1 enfant jouet** : côté mère —
   `EventEnvelope` (contrat versionné `SCHEMA_VERSION`), `HmacSigner`, job `RelayDomainEvents`
   (auto-redispatché, draine l'outbox par `sequence`, route via `SubscriberResolver`, tamponne
   `published_at`, heartbeat planifié `everyMinute`), job `DeliverDomainEvent` (file `sync`,
   `tries`/backoff, POST signé `X-Signature`=HMAC-SHA256 du corps brut + en-têtes `X-Event-Id`/
   `X-Event-Sequence`/`X-Aggregate`/`X-Schema-Version`), supervisor Horizon `sync` dédié. Les
   **coordonnées de livraison** différées à l'incr. 2 sont posées : table sœur
   `application_sync_endpoints` (`endpoint_url`, `secret` `#[Hidden]`, `sync_enabled`) +
   `ApplicationSyncEndpoint`, et `Subscriber`/`SubscriptionResolver` portent désormais
   `endpointUrl`+`secret` en ne résolvant que les souscripteurs `sync_enabled`. Côté enfant
   (`dailyapps/portal-shared-schema`) — table `processed_events`, modèle `ProcessedEvent`,
   trait `ReadOnlyReplica` (le nom `ReadOnly` est interdit : `readonly` est un mot-clé
   réservé, insensible à la casse) + `ReplicaIsReadOnlyException`, écrivain `ReplicaWriter`
   (`DB::table()->upsert()` filtré aux colonnes locales) et contrôleur `HandleDomainEvent`
   (vérif HMAC → 401 sinon, dédup `X-Event-Id`, upsert d'état complet en transaction).
   ✅ critère : un event signé est appliqué dans le réplica ; **rejouer le même `X-Event-Id`
   renvoie `duplicate` et ne produit aucune 2ᵉ mutation** ; mauvaise signature rejetée (401) ;
   relais ne dispatche que vers les `Application` `sync_enabled` et tamponne `published_at`.
5. **Amorçage + cycle de souscription + reconcile** : peupler un enfant neuf via REST
   snapshot (scopé aux orgs souscrites), noter la `sequence` haute, injecter un événement
   obsolète et vérifier qu'il est ignoré ; tester `SubscriptionGranted` → backfill ciblé et
   `SubscriptionRevoked` → purge ; lancer `ReconcileFromMother` après une perte simulée de
   webhook et confirmer la convergence.
6. **Sécurité** : test négatif (mauvaise signature → rejet), test d'isolation (une
   `Application` ne reçoit pas les événements d'un `client_id` auquel elle n'est pas
   souscrite), vérifier que les champs `#[Hidden]` n'apparaissent jamais dans un payload.

> Conventions à respecter pendant l'implémentation : `laravel:no-observers`,
> `laravel:no-fat-models`, `laravel:no-cascade-delete`, `laravel:softdeletes-require-prunable`,
> classes Action comme séminaire d'écriture, tests par layer via `php artisan osdd:phpunit`,
> exécution dans le conteneur (`make shell p=portal-api`).
