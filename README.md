# Opus — As Simple As Possible / As Soon As Possible

Opus est le framework PHP mutualisable du workspace MAESTRO.

## Rôle

Opus fournit le socle framework générique :

- Application / Kernel
- SiteResolver
- Router
- FSM
- ACL
- Controller / Action
- ScoreTemplate rendering
- Renderers
- I18N
- REST contracts

## Contrat

Opus est indépendant de MO_KB, MAESTRO, LogAndPlay et des sites applicatifs.

Opus ne contient pas :

- route métier MO_KB
- thème métier
- chemin absolu projet
- fallback silencieux
- secret
- vendor committé
- cache runtime

## ScoreTemplate

ScoreTemplate est le moteur de templating natif cible d'Opus. Les templates `.score` représentent uniquement des ViewModels validés : pas de PHP, pas de service, pas de base de données, pas de fallback vers Twig, Smarty, x64 ou un adapter legacy.

Twig peut rester temporairement présent pour migration applicative, mais la cible framework est ScoreTemplate.

## Documentation

Chaque API publique doit être documentée façon Doxygen/phpDocumentor afin de générer les Reference Books.

NO DOC CONTRACT, NO PATCH.
