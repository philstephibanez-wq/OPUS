# P112Q3A — Router / FSM / ACL Integration Plan

Statut: PLAN D'INTÉGRATION — AUCUN PATCH CODE FRAMEWORK DANS CE PALIER  
Portée cible future: ASAP framework  
Prochain palier recommandé: `P112Q3B_ASAP_SECURE_DISPATCH_GATE_BASELINE`

---

## 1. Objectif P112Q3B

Mettre en place un passage obligatoire avant dispatch controller:

```text
SecureDispatchGate
```

Il reçoit:

```text
Request
SiteDefinition
RouteMatch
SiteSecurityPolicy
```

Il retourne:

```text
SecureDispatchDecision
```

Le dispatcher ne doit être appelé que si cette décision est positive.

---

## 2. Nouveaux objets recommandés

```text
ASAP\Security\SecureDispatchGate
ASAP\Security\SecureDispatchDecision
ASAP\Security\NavigationGuard
ASAP\Security\RouteSecurityPolicy
ASAP\Security\RouteSecurityPolicyResolver
ASAP\Security\SecurityAuditEvent
```

Ne pas modifier les responsabilités existantes:

```text
Router = match route uniquement
FSM = états/transitions uniquement
ACL = droits uniquement
ControllerDispatcher = dispatch uniquement
Renderer = représentation uniquement
```

---

## 3. Évolution route-aware

### 3.1 Router

Faire évoluer `Router::fromXml()` pour hydrater explicitement:

```text
methods
host
locale
format
acl
fsmGuard
priority
source
```

Le constructeur `RouteDefinition` possède déjà ces champs. Le patch doit donc rester léger.

### 3.2 RouteMatch

Ajouter des champs en lecture seule:

```text
acl: ?string
fsmGuard: ?string
format: string
methods: string[]
```

Contrat: `RouteMatch` reste data-only.

---

## 4. Policy route-aware

Le XML sécurité doit pouvoir déclarer:

```xml
<routes>
  <route name="admin.dashboard" acl="admin.dashboard:read" fsmGuard="NAVIGATE_ADMIN" />
</routes>
```

ou équivalent strict, à valider selon convention XML actuelle.

Interdit:

```text
route sans policy qui passe quand même
acl/fsmGuard absent remplacé silencieusement
policy globale qui autorise tout par défaut
```

---

## 5. Navigation FSM

Créer une FSM dédiée navigation, séparée de la FSM site-security:

```text
NAV_IDLE -- ROUTE_REQUESTED --> NAV_ROUTE_CANDIDATE
NAV_ROUTE_CANDIDATE -- ROUTE_ALLOWED --> NAV_ALLOWED
NAV_ROUTE_CANDIDATE -- ACL_DENIED --> NAV_DENIED
NAV_ROUTE_CANDIDATE -- SECURITY_LOCKED --> NAV_LOCKED
```

La FSM ne décide pas les droits. Elle reçoit le signal qui résulte des guards.

---

## 6. ACL comme guard officiel

L'ACL doit décider:

```text
role + resource + privilege + context(route, method, path, fsm_state)
```

Puis le résultat ACL devient un signal de navigation:

```text
ACL_ALLOWED -> ROUTE_ALLOWED
ACL_DENIED  -> NAV_DENIED
```

---

## 7. Mermaid réel

Après P112Q3B, ajouter P112Q3C:

```text
ASAP\Fsm\Diagram\MermaidFsmDiagramExporter
```

Il doit lire:

```text
StateDefinition[]
TransitionDefinition[]
initialState
```

et produire:

```text
stateDiagram-v2
    [*] --> SITE_OPEN
    SITE_OPEN --> SITE_LOCKDOWN: AUTH_ATTACK_DETECTED
```

Le RefBook affichera ce Mermaid, sans générer la logique FSM lui-même.

---

## 8. Tests minimaux P112Q3B

Créer un smoke sans HTTP complexe:

```text
- route publique autorisée
- route admin refusée pour role public
- route admin autorisée pour role admin
- route inconnue = ASAP_ROUTE_NOT_FOUND
- fsmGuard inconnu = erreur explicite
- acl policy inconnue = erreur explicite
- dispatcher non appelé si gate refuse
```

---

## 9. Rollback

P112Q3B doit être réversible par suppression des nouveaux fichiers et restauration de:

```text
framework/Asap/Application/Application.php
framework/Asap/Routing/Router.php
framework/Asap/Routing/RouteMatch.php
```

Aucune migration BDD.
Aucune modification Apache/UwAmp.
Aucun `.htaccess`.

