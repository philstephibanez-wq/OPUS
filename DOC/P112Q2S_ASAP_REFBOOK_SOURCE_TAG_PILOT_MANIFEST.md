# P112Q2S — ASAP RefBook Source Tag Pilot and Manifest

## But

Installer le premier vrai pipeline documentaire officiel:

```text
ASAP source tags
        ↓
manifest source officiel
        ↓
ASAP_REF_BOOK data
        ↓
futur renderer ASAP/Twig
```

## Domaine pilote

`FSM`

Les premières balises `ASAP_REFBOOK` sont intégrées directement dans les sources `framework/Asap`.

## Règle workspace

Les `.md` restent utiles pour le travail, les handoffs, contrats et notes.
Ils ne remplacent jamais les balises de documentation intégrées aux sources pour générer une documentation technique officielle.

## Manifest

Le manifest est généré par:

```cmd
php tools\refbook\asap_refbook_manifest_build.php H:\ASAP --refbook=H:\ASAP_REF_BOOK
```

## Suite

`P112Q2T_ASAP_REFBOOK_TWIG_MANIFEST_RENDERER`

<!-- BEGIN P112Q2S1_WORKSPACE_DEV_MARKERS -->
## Workspace development workflow markers

- WORKSPACE_RULE_MD_NEVER_REPLACE_SOURCE_TAGS=1
- WORKFLOW_APPLY_TEST_STAGE_COMMIT_PUSH=1
- WORKFLOW_STAGE_EXACT_AFTER_TEST_OK=1
- WORKFLOW_COMMIT_PUSH_AFTER_PHASE_VALIDATED=1

Scope: ASAP Q2S source tag pilot
<!-- END P112Q2S1_WORKSPACE_DEV_MARKERS -->
