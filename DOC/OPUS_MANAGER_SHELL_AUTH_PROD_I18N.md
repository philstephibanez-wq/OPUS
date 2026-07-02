# OPUS Manager — Auth / Prod / I18N

Contrat : `OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE`

## Décision

OPUS Manager fait partie de la livraison dev OPUS.

Cette brique durcit le shell OPUS Manager :

- auth centrale minimale
- routes dédiées `SignInController` et `LogoutController`
- dev/prod strict
- production sans profiler/debug
- i18n prête pour toutes les langues officielles UE + ukrainien (`uk`)
- Create Site Wizard conservé comme entrée principale

## Auth

En dev :

```text
admin / admin
```

En prod :

```text
environment.php doit dériver de environment.prod.example.php
admin_user doit être défini
admin_password_hash doit être défini avec password_hash()
aucun fallback silencieux
```

## Prod

En prod :

- aucun profiler
- aucun debug
- `profiler=1`, `_profiler=1` et `profile=1` sont supprimés du contexte
- aucune toolbar debug
- auth obligatoire

## I18N

Langues supportées :

```text
bg hr cs da nl en et fi fr de el hu ga it lv lt mt pl pt ro sk sl es sv uk
```

`uk` est le code ukrainien.

## Livraison dev

OPUS Manager est inclus dans la livraison dev OPUS avec :

- `sites/opus-manager`
- `CreateSiteController`
- shell navigation
- auth minimale
- i18n prête
- dev/prod strict
- smokes dédiés

## Prochaines étapes

- ACL/RBAC effectif par route
- Ref Book complet OPUS Manager
- User Book complet OPUS Manager
- tests HTTP serveur
- tests Composer installation serveur
