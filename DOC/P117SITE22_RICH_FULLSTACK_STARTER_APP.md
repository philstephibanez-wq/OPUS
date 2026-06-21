# P117SITE22 — Rich fullstack starter app

## Objectif

Faire évoluer `composer opus:create-application` et son alias `composer opus:create-site` vers une application OPUS de test riche, visible dans le navigateur via le serveur PHP interne.

## Contrat

Une application OPUS générée est un site/projet fullstack autonome avec séparation stricte :

```text
frontend/ = représentation
middle/   = routage, transport, sécurité, contrats request/response
backend/  = traitement métier, données, modules, runners, jobs
```

## Ce qui est généré

- plusieurs views frontend : accueil, architecture, catalogue, détail catalogue, composants, API/sécurité, backoffice, documentation ;
- plusieurs layouts : public et backoffice ;
- plusieurs sections ;
- navigation multilingue ;
- langues FR / EN / ES ;
- un module backend réel de démonstration : `Catalog` ;
- un endpoint API visible : `/api/catalog` ;
- une couche `middle/` avec routes, API contracts, security pipeline et FSM gate ;
- un front controller capable de composer layout + view + sections + viewmodel ;
- des routes navigables avec le serveur interne PHP.

## Interdictions maintenues

- pas de logique métier dans les views, layouts, sections ou components ;
- pas de rendu HTML dans les services/actions/repositories backend ;
- pas de backoffice confondu avec backend ;
- pas de termes blog/CMS comme squelette standard ;
- pas de fallback silencieux I18N : une clé manquante casse explicitement.

## Test manuel attendu

```cmd
composer opus:create-application -- demo-fullstack --write --serve --port 8791
```

Puis vérifier :

- `http://127.0.0.1:8791/`
- `http://127.0.0.1:8791/architecture?lang=en`
- `http://127.0.0.1:8791/catalog?lang=fr`
- `http://127.0.0.1:8791/catalog/module-catalog?lang=es`
- `http://127.0.0.1:8791/components?lang=fr`
- `http://127.0.0.1:8791/security?lang=fr`
- `http://127.0.0.1:8791/backoffice?lang=fr`
- `http://127.0.0.1:8791/documentation?lang=fr`
- `http://127.0.0.1:8791/api/catalog?lang=en`
