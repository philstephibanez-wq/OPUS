# OPUS Manager — OPUS-only realignment

Contrat : `OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_CORE`

## Décision non négociable

```text
OPUS = framework.
OPUS Manager = AMS, Application Management System.
OPUS Manager est une application OPUS.
OPUS, encore OPUS, rien qu’OPUS.
```

## Règle absolue

OPUS Manager ne doit pas recréer de mini-framework.

## Interdits

- mini-i18n locale
- mini-auth locale
- mini-ACL locale
- mini-registry de navigation
- mini-router applicatif hors OPUS
- templates HTML concaténés dans les controllers
- gestion de mot de passe hors Identity / ACL / RBAC OPUS
- navigation hors FSM / CL OPUS

## Mapping obligatoire

| Besoin OPUS Manager AMS | Brique OPUS obligatoire |
| --- | --- |
| langue / traduction | OPUS I18N |
| rendu HTML | OPUS templates / layouts |
| login / session / mot de passe | OPUS Identity |
| droits / menus / modules | OPUS ACL / RBAC |
| navigation / états / transitions | OPUS FSM / CL |
| création site/page/package | OPUS Composer / recettes |
| logs / diagnostics | OPUS logging / diagnostics |
| documentation | OPUS Ref Book / User Book |
| tests | OPUS smokes |

## Migration

Audit OPUS réel → mapping des briques → adapters OPUS explicites si nécessaire → suppression mini-framework → smoke OPUS-only bloquant.

OPUS Manager est un AMS : il pilote OPUS, il n’invente rien hors OPUS.
