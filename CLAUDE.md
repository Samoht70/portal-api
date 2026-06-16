## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).

## Structure OSDD — dossiers de layers

Les layers (chaque domaine = un package composer `"type": "layer"`) vivent sous trois dossiers, tous déclarés dans `technical/osdd/config/osdd.php` (`layers.paths`) et exposés comme path-repos dans le `composer.json` racine :

- `functional/` — domaines métier (users, organizations, applications…).
- `technical/` — capacités techniques transverses génériques (osdd, access-control, oauth-server, horizon…).
- `dailyapps/` — packages propres à l'écosystème dailyapps, ni domaine métier ni infra générique. Ex. `dailyapps/portal-shared-schema` (macros de schéma partagées mère↔enfants, cf. `technical/ARCHITECTURE-SYNC.md`).

Pour créer un layer : `php artisan osdd:layer`. Pour ajouter un dossier de layers : l'ajouter à `layers.paths` (osdd.php) ET aux `repositories` path du composer racine.
