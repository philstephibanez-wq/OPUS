# OPUS — Site Standard Contract

Contrat : `OPUS_SITE_STANDARD_CONTRACT_CORE`

## Portée

Ce contrat est obligatoire pour tous les sites OPUS présents et futurs.

Aucun site OPUS ne doit utiliser une structure spécifique improvisée.

## Structure canonique

```text
sites/<site>/
  application/
    default/
      helpers/
      local/
      models/
      templates/
      views/

    <controller>/
      acl/
      helpers/
      javascript/
      local/
        <locale>/
      models/
      templates/
      views/

  config/

  www/
    index.php
    asset/
      css/
      js/
      themes/
        <theme>/
```

## Règles

- Le répertoire applicatif s'appelle `application`, pas `src`.
- Le répertoire web public s'appelle `www`, pas `public`.
- Les assets publics vont dans `www/asset`.
- Les CSS vont dans `www/asset/css`.
- Les JS vont dans `www/asset/js`.
- Les thèmes vont dans `www/asset/themes`.
- `application/default` contient uniquement les parties communes, pas un fourre-tout.
- Chaque controller/fonctionnalité a son propre répertoire sous `application`.
- Chaque controller possède ses propres `acl`, `helpers`, `javascript`, `local`, `models`, `templates` et `views` si nécessaire.
- Les templates et views appartiennent à OPUS, pas aux controllers en HTML concaténé.
- L'i18n utilise OPUS `local` / i18n, pas un service local improvisé.
- L'auth, admin, mot de passe, ACL et RBAC utilisent OPUS.
- La navigation utilise OPUS FSM / CL.

## OPUS Manager AMS

OPUS Manager est une application OPUS de type AMS.

Il doit respecter exactement ce contrat comme n'importe quel autre site OPUS.

```text
OPUS, encore OPUS, rien qu’OPUS.
```
