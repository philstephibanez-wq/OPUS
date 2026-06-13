# Opus Framework Core

## Responsabilité

Ce dossier contient uniquement le framework générique Opus.

## Couches souveraines

- ACL : autorisations
- FSM : états et transitions
- SITE : résolution de site
- ROUTING : résolution de route
- MODULE : registre de modules
- CONTROLLER : contrat controller/action
- TEMPLATE : adaptateurs de templates
- RENDER : représentation finale
- I18N : internationalisation

## Interdictions

- aucun code métier MO_KB
- aucun chemin absolu local
- aucun fallback silencieux
- aucune représentation qui décide le métier
