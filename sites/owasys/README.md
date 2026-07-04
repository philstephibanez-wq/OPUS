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

## OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE

- OPUS Manager fait partie de la livraison dev OPUS.
- Auth centrale minimale activée.
- En dev : compte `admin` / `admin`.
- En prod : aucun fallback ; configurer `environment.php` depuis `environment.prod.example.php` avec un hash valide.
- En prod : aucun profiler/debug, même avec `profiler=1`.
- I18N prête pour toutes les langues officielles UE + ukrainien (`uk`).

## OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE

- Finalise auth/sign-in/logout/i18n pour OPUS Manager.
- Sign in dev : `admin / admin`.
- Le sélecteur de langue suffit ; pas de badge `Langue : ...` dupliqué.
- En prod, les paramètres profiler/debug sont filtrés.
