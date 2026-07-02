# OPUS Manager

Contrat : `OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE`

OPUS Manager est le backoffice central OPUS.

L’entrée principale utilisateur est :

```text
Créer un site avec OPUS
```

Les modules techniques restent disponibles derrière le shell, avec un controller dédié par fonctionnalité.

## Démarrage local

```text
php -S 127.0.0.1:8079 -t sites/opus-manager/public sites/opus-manager/public/router.php
```

Puis ouvrir :

```text
http://127.0.0.1:8079/opus-manager/create-site
```

## Règles

- Un controller par fonctionnalité/page.
- CreateSiteController est l’entrée principale utilisateur.
- LSTSAR Manager et ODBC Manager sont réutilisés, pas recréés.
- Les briques OPUS sont les seules briques autorisées.
- En prod : aucun profiler/debug.
- Ref Book et User Book doivent documenter le shell.
