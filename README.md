# Opus Framework 8.1.0 Lysenko

Opus est le framework PHP mutualisable du workspace MAESTRO.

## Identité

Nom actif : OPUS Framework.

Version cible : 8.1.0 Lysenko.

ASAP : ancien nom historique, legacy uniquement.

Aucun nouveau développement ne doit présenter ASAP comme identité active du framework.

## Rôle

Opus fournit le socle framework générique :

- Application / Kernel
- SiteResolver
- Router
- FSM
- ACL
- Controller / Action
- Template adapters
- Renderers
- I18N
- REST contracts

## Topologie officielle

OPUS utilise un core partagé et des packages optionnels officiels.

```text
framework/Opus/              core framework partagé
packages/opus-refbook/       site RefBook optionnel officiel
packages/opus-user-guide/    futur guide utilisateur optionnel
```

Règle : un seul framework OPUS, plusieurs sites/packages OPUS, aucune duplication du core dans les packages.

## Packages optionnels

Chaque package optionnel doit déclarer sa dépendance au core OPUS via `opus-package.json`.

Un package peut être installé séparément, mais il ne doit jamais embarquer `framework/Opus/`.

Le contrat de manifest et le contrat d'installation sont documentés dans :

```text
packages/OPUS_PACKAGE_MANIFEST_CONTRACT.md
packages/OPUS_PACKAGE_INSTALL_CONTRACT.md
packages/opus-package.schema.json
```

Validation maintenance :

```text
php tools/validate_opus_packages.php
```

Installation maintenance, exemple dry-run :

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS_REF_BOOK --opus-root=H:\OPUS --dry-run
```

Installation réelle :

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS_REF_BOOK --opus-root=H:\OPUS
```

L'installation écrit un `opus-runtime.local.json` dans le site cible. Ce fichier pointe explicitement vers le core OPUS partagé et garde `fallback_allowed=false`.

## Licence / droits

OPUS suit le profil de licence défini dans `LICENSE_INTENT.md` :

```text
Copyright © Philippe Stéphane Ibanez
source-available
usage personnel et non commercial libre
usage commercial sous licence commerciale payante
royalties obligatoires pour usage commercial
pas open source OSI sauf décision future explicite
```

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
- duplication du framework dans les packages optionnels

## Documentation

Chaque API publique doit être documentée façon Doxygen/phpDocumentor afin de générer les Reference Books.

NO DOC CONTRACT, NO PATCH.
