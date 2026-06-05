# P112Q1 — ASAP Router Attribute Compiler Contract

## Objectif

Installer le contrat moderne du routeur ASAP : routes explicites, attributes PHP8,
compilation contrôlée et manifest runtime.

## Décision d'architecture

L'autoloader ne compile pas les routes pendant l'autoload.

Il peut fournir une cartographie des classes, représentée par :

`ASAP\Routing\ClassIndex`

Le scanner d'attributes utilise cette cartographie, puis le compiler écrit un
manifest PHP stable.

## Nouveaux objets

- `ASAP\Routing\Route`
- `ASAP\Routing\ClassIndex`
- `ASAP\Routing\AttributeRouteProvider`
- `ASAP\Routing\RouteManifestCompiler`
- `ASAP\Routing\RouteCompilerException`

## Route attribute

```php
#[Route(
    path: '/kb/search',
    name: 'kb.search',
    methods: ['GET'],
    acl: 'kb.read'
)]
public function index(): Response
{
}
```

## Manifest runtime

Le manifest généré est un fichier PHP :

`var/cache/asap/routes.manifest.php`

Le runtime doit lire ce manifest au lieu de rescanner les controllers.

## Règles bloquantes

- nom de route dupliqué : erreur
- conflit path + method + host + locale : erreur
- controller introuvable : erreur
- manifest absent : erreur
- aucune route : erreur

Aucun fallback silencieux.

## Hors scope P112Q1

- intégration complète Application/Controller/SITE
- remplacement du routeur runtime existant
- ACL/FSM/BDD/SMTP
