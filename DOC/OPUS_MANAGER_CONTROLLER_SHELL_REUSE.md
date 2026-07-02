# OPUS Manager — Controller Shell Reuse

Contrat : `OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE`

## Objectif

Créer le shell backoffice OPUS Manager en récupérant l’existant.

Le shell n’est pas une réécriture de LSTSAR Manager ou ODBC Manager. Il les expose comme modules internes et les relie aux routes OPUS existantes.

## Navigation utilisateur

Entrée principale :

```text
Créer un site avec OPUS
```

Navigation claire :

- Créer
  - Créer un site
  - Créer un package
- Identité
  - Users / Identity
  - ACL
  - RBAC
  - SSO
  - Sessions
  - Auth Audit
- Moteurs
  - FSM
  - CL
  - Models
- Données
  - Database
  - ODBC Manager
  - LSTSAR Manager
- Installation
  - Composer
- Documentation
  - Ref Book
  - User Book
- Exploitation
  - Logs
  - Diagnostics

## Réutilisation

- ODBC Manager pointe vers la route OPS existante `/opus-lstsar-manager/odbc-manager`.
- LSTSAR Manager pointe vers les routes OPS existantes `/opus-lstsar-manager/chain` et `/opus-lstsar-manager/operations`.
- FSM, CL et Models pointent vers les routes OPS existantes correspondantes.
- CreateSiteController orchestre les modules au lieu de les recréer.

## Production

En mode `OPUS_ENV=prod` :

- `profiler`, `_profiler` et `profile` sont supprimés du contexte de requête.
- Aucun profiler n’est affiché.
- La page rappelle que le profiler est interdit.

## Prochaines étapes

- Brancher l’auth centrale OPUS Manager.
- Brancher ACL/RBAC par route.
- Remplacer les pages placeholder par les services OPUS existants.
- Générer le Ref Book et le User Book depuis ce shell.
- Ajouter les tests installation serveur Composer.
