# P112Q3B — ASAP Secure Dispatch Gate Baseline

## Rôle

Introduire le passage obligatoire `SecureDispatchGate` entre la route candidate et le dispatch controller/action.

## Contrat secure by design

```text
REQUEST
  -> SITE RESOLVER
  -> ROUTE CANDIDATE
  -> SECURE DISPATCH GATE
       -> FSM signal
       -> ACL decision
  -> CONTROLLER DISPATCHER
```

Règles appliquées :

- aucun controller/action ne doit être appelé avant validation du gate ;
- une route inconnue casse explicitement via le Router ;
- une transition FSM absente casse explicitement via `StateMachine` ;
- une règle ACL absente refuse explicitement ;
- une metadata ACL de route mal formée casse explicitement ;
- aucun fallback silencieux n'est ajouté.

## Détail technique

### `Application`

`Application::run()` résout maintenant la route candidate avant la sécurité route-aware :

```text
SiteResolver
SiteSecurityPolicyLoader
Router::match
SecureDispatchGate::assertAllowed
ControllerDispatcher::dispatch
```

### `Router`

`Router::fromXml()` hydrate maintenant les champs déjà prévus par `RouteDefinition` :

- `acl`
- `fsmGuard`
- `methods`
- `host`
- `locale`
- `format`
- `priority`
- `source`

Formats ACL supportés :

```xml
<route acl="page:read" />
<route acl="admin:page:read" />
<route><security acl="page:read" /></route>
<route><acl resource="page" privilege="read" /></route>
```

Formats FSM supportés :

```xml
<route fsmGuard="ROUTE_HOME" />
<route fsm_guard="ROUTE_HOME" />
<route><security fsmGuard="ROUTE_HOME" /></route>
<route><fsm signal="ROUTE_HOME" /></route>
```

## Compatibilité contrôlée

Si une route ne déclare pas encore de metadata route-aware, le gate utilise la policy site déjà déclarée dans `security.xml` :

- `SiteSecurityPolicy::requestSignal`
- `SiteSecurityPolicy::role`
- `SiteSecurityPolicy::resource`
- `SiteSecurityPolicy::privilege`

Ce n'est pas un fallback silencieux : c'est la policy site explicite existante. Une policy absente ou incomplète casse déjà via `SiteSecurityPolicyLoader` / `SiteSecurityPolicy`.

## Recette robotisée Panther-aware

Le palier ajoute :

```text
tools/recipes/p112q3b_secure_dispatch_gate_panther_recipe.php
tools/recipes/p112q3b_secure_dispatch_gate_recipe.json
```

Variables :

```text
ASAP_P112Q3B_PANTHER_URL       URL HTTP locale à ouvrir
ASAP_P112Q3B_EXPECT_TEXT       texte attendu optionnel
ASAP_P112Q3B_PANTHER_REQUIRED  1 = Panther obligatoire, sinon skip explicite autorisé
```

La recette n'installe aucune dépendance et ne fabrique pas de faux succès navigateur.

## Validation

```bat
cd /d H:\ASAP
php tools\smoke\p112q3b_secure_dispatch_gate_smoke.php
```

Résultat attendu :

```text
P112Q3B_SECURE_DISPATCH_GATE_SMOKE_OK
```

Recette Panther :

```bat
cd /d H:\ASAP
set ASAP_P112Q3B_PANTHER_URL=http://127.0.0.1/ASAP_REF_BOOK/?lang=fr
set ASAP_P112Q3B_EXPECT_TEXT=ASAP
php tools\recipes\p112q3b_secure_dispatch_gate_panther_recipe.php
```

## Rollback

Restaurer les fichiers modifiés depuis Git :

```bat
git checkout -- framework\Asap\Application\Application.php framework\Asap\Routing\Router.php framework\Asap\Routing\RouteMatch.php
```

Supprimer les nouveaux fichiers si nécessaire :

```text
framework/Asap/Security/SecureDispatchGate.php
framework/Asap/Security/SecureDispatchDecision.php
tools/smoke/p112q3b_secure_dispatch_gate_smoke.php
tools/smoke/run_p112q3b_secure_dispatch_gate_smoke.cmd
tools/recipes/p112q3b_secure_dispatch_gate_panther_recipe.php
tools/recipes/run_p112q3b_secure_dispatch_gate_panther_recipe.cmd
tools/recipes/p112q3b_secure_dispatch_gate_recipe.json
DOC/P112Q3B_*.md
```
