
# P112Q3A — ASAP Secure-by-design Audit

Statut: AUDIT CONTRACTUEL — AUCUN PATCH CODE FRAMEWORK  
Portée: ASAP framework public `philstephibanez-wq/ASAP`  
Branche observée: `master`  
Palier source observé: `P112Q2T_ASAP_REFBOOK_ALL_SOURCE_TAGS_BASELINE`  
Date: 2026-06-08

---

## 1. Contrat appliqué

Ce palier applique le contrat MAESTRO_WORKSPACE:

```text
NO SOURCE OF TRUTH, NO PATCH
NO CONTEXT, NO PATCH
NO LIVE TARGET, NO PATCH
NO REAL FILE VISIBLE, NO PATCH
NO HYPOTHESIS PATCH
0 fallback silencieux
0 tolérance implicite
1 couche = 1 métier
NO DOC CONTRACT, NO PATCH
```

Décision de ce palier: **audit seulement**.  
Aucune classe ASAP n'est modifiée dans P112Q3A.

---

## 2. Verdict court

ASAP possède déjà un **socle secure-by-design partiel**:

```text
Application
  -> SiteResolver
  -> SiteSecurityPolicyLoader
  -> FsmGuard
  -> AclGuard
  -> Router
  -> ControllerDispatcher
```

Ce socle est sain parce que:

- la sécurité est appelée avant dispatch controller;
- la FSM est portée par un moteur `ASAP\Fsm\StateMachine`;
- l'ACL est portée par `ASAP\Acl\AccessControl`;
- les erreurs importantes cassent explicitement;
- il n'y a pas de route fallback implicite dans `Router::match`;
- `AccessControl` applique un deny explicite si aucune règle n'existe.

Mais il n'est **pas encore le pilotage FSM complet demandé** pour navigation + sécurité + ACL, car:

- la route candidate est résolue **après** la FSM/ACL site-level;
- `RouteDefinition` possède déjà des champs `acl` et `fsmGuard`, mais `Router::fromXml()` ne les hydrate pas encore;
- `RouteMatch` ne porte pas encore la décision sécurité/navigation complète;
- l'ACL guard utilise encore la policy globale du site (`role`, `resource`, `privilege`) au lieu d'une policy route-aware;
- il n'existe pas encore un passage unique nommé et observable du type `SecureDispatchGate`;
- le générateur Mermaid des transitions réelles n'est pas encore branché au runtime.

Conclusion: **P112Q3B doit intégrer un SecureDispatchGate route-aware**, sans casser l'existant.

---

## 3. État réel observé

### 3.1 Application

`ASAP\Application\Application::run()` orchestre déjà:

```text
SiteResolver
SiteSecurityPolicyLoader
FsmGuard
AclGuard
Router
ControllerDispatcher
```

Contrat observé:

```text
Application orchestre seulement.
Elle ne rend pas directement, ne décide pas les règles ACL et ne compense pas silencieusement une config manquante.
```

### 3.2 FSM guard

`ASAP\Security\FsmGuard`:

```text
- construit une StateMachine depuis SiteSecurityPolicy
- applique exactement le requestSignal déclaré
- retourne l'état atteint
```

Limite actuelle: le signal appliqué vient de la policy globale, pas d'une route candidate ou d'un événement navigation normalisé.

### 3.3 ACL guard

`ASAP\Security\AclGuard`:

```text
- construit AccessControl depuis SiteSecurityPolicy
- évalue role/resource/privilege
- enrichit le contexte avec path, method, fsm_state
- throw AccessControlException si refus
```

Limite actuelle: `role/resource/privilege` sont globaux policy, pas route-aware.

### 3.4 Router

`ASAP\Routing\Router::match()`:

```text
- calcule localPath
- teste les routes déclarées
- retourne RouteMatch
- throw ASAP_ROUTE_NOT_FOUND si aucune route
```

C'est conforme au `0 fallback silencieux`.

### 3.5 RouteDefinition

`RouteDefinition` contient déjà les métadonnées nécessaires à la prochaine étape:

```text
acl: ?string
fsmGuard: ?string
priority: int
source: string
```

Mais `Router::fromXml()` n'alimente pas encore ces champs.

---

## 4. Gap principal

Le pipeline actuel est plutôt:

```text
REQUEST
  -> SITE RESOLVER
  -> SITE SECURITY POLICY
  -> SITE FSM GUARD
  -> SITE ACL GUARD
  -> ROUTER
  -> DISPATCHER
```

Le pipeline secure-by-design cible doit devenir:

```text
REQUEST
  -> SITE RESOLVER
  -> ROUTE CANDIDATE
  -> SECURITY POLICY
  -> SECURE DISPATCH GATE
       -> SECURITY FSM
       -> NAVIGATION FSM
       -> ACL GUARDS
  -> CONTROLLER DISPATCHER
```

Pourquoi: la navigation et l'ACL doivent être décidées à partir de la route demandée, pas seulement d'un état global site.

---

## 5. Règles P112Q3B à respecter

```text
Default deny.
Route non déclarée = ASAP_ROUTE_NOT_FOUND.
Route sans security policy explicite = ASAP_ROUTE_SECURITY_POLICY_MISSING.
FSM guard inconnue = ASAP_ROUTE_FSM_GUARD_UNKNOWN.
ACL policy inconnue = ASAP_ROUTE_ACL_POLICY_UNKNOWN.
Transition navigation absente = FSM_TRANSITION_NOT_ALLOWED.
ACL deny = ACL_ACCESS_DENIED.
Aucun ControllerDispatcher avant SecureDispatchGate.
Aucun template ne consulte ACL/FSM.
Aucun Router ne décide les droits.
Aucune ACL ne décide la navigation.
```

---

## 6. Décision P112Q3A

P112Q3A valide que:

- la base actuelle permet une intégration propre;
- il ne faut pas réécrire FSM/ACL;
- il faut ajouter un gate sécurisé route-aware;
- il faut hydrater les métadonnées route existantes;
- il faut produire ensuite le Mermaid depuis les transitions réelles.

