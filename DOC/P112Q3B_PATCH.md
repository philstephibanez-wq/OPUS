# P112Q3B — PATCH

## Scope

Repository cible : `ASAP`.

## Fichiers modifiés

```text
framework/Asap/Application/Application.php
framework/Asap/Routing/Router.php
framework/Asap/Routing/RouteMatch.php
```

## Fichiers ajoutés

```text
framework/Asap/Security/SecureDispatchGate.php
framework/Asap/Security/SecureDispatchDecision.php
tools/smoke/p112q3b_secure_dispatch_gate_smoke.php
tools/smoke/run_p112q3b_secure_dispatch_gate_smoke.cmd
tools/recipes/p112q3b_secure_dispatch_gate_panther_recipe.php
tools/recipes/run_p112q3b_secure_dispatch_gate_panther_recipe.cmd
tools/recipes/p112q3b_secure_dispatch_gate_recipe.json
DOC/P112Q3B_ASAP_SECURE_DISPATCH_GATE_BASELINE.md
DOC/P112Q3B_PATCH.md
DOC/P112Q3B_CHANGELOG.md
DOC/P112Q3B_TODO.md
```

## Principe

Le patch ajoute un gate officiel avant dispatch controller/action.

```text
route candidate -> SecureDispatchGate -> controller dispatch
```

Le gate consomme :

- la policy site existante ;
- la route candidate ;
- les metadata route-aware `acl` et `fsmGuard` quand elles sont déclarées.

## Hors scope

- Pas de modification Apache / UwAmp.
- Pas de modification BDD.
- Pas d'installation Composer.
- Pas de migration automatique des routes existantes.
- Pas de dépendance Panther ajoutée automatiquement.
