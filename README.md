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

OPUS utilise un core partagé, des packages optionnels officiels et des sites installés sous une arborescence lisible.

```text
framework/Opus/              core framework partagé unique
packages/                    packages officiels OPUS installables
packages/opus-refbook/       site RefBook optionnel officiel
packages/opus-user-guide/    futur guide utilisateur optionnel
sites/                       sites installés / instances runtime
config/                      templates de configuration non secrets
var/                         cache/logs/tmp locaux, livrés vides
tools/                       outils CLI OPUS
tests/                       tests internes dev uniquement, hors livrables
```

Règle : un seul framework OPUS, plusieurs sites/packages OPUS, aucune duplication du core dans les packages ou les sites.

Si OPUS est placé sous une racine web locale comme `H:\UwAmp\www\OPUS`, le serveur web doit exposer uniquement les dossiers `sites/*/public/`, jamais `framework/`, `packages/`, `tools/`, `tests/`, `config/` ou `var/`.

## Packages optionnels

Chaque package optionnel doit déclarer sa dépendance au core OPUS via `opus-package.json`.

Un package peut être installé séparément, mais il ne doit jamais embarquer `framework/Opus/`.

Le contrat de manifest, le contrat d'installation et le profil de livraison sont documentés dans :

```text
packages/OPUS_PACKAGE_MANIFEST_CONTRACT.md
packages/OPUS_PACKAGE_INSTALL_CONTRACT.md
packages/opus-package.schema.json
DELIVERY_PROFILE.md
```

Validation packages :

```text
php tools/validate_opus_packages.php
```

Validation layout dev :

```text
php tools/validate_opus_delivery_layout.php --root=H:\UwAmp\www\OPUS --mode=dev
```

Validation layout livrable :

```text
php tools/validate_opus_delivery_layout.php --root=H:\UwAmp\www\OPUS --mode=delivery
```

Installation maintenance, exemple dry-run :

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS\sites\opus-refbook --opus-root=H:\UwAmp\www\OPUS --dry-run
```

Installation réelle :

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS\sites\opus-refbook --opus-root=H:\UwAmp\www\OPUS
```

L'installation écrit un `opus-runtime.local.json` dans le site cible. Ce fichier pointe explicitement vers le core OPUS partagé et garde `fallback_allowed=false`.

## Livraison

Une livraison OPUS core doit conserver l'arborescence utile (`sites/`, `packages/`, `config/`, `var/`) même si certains dossiers ne contiennent que des README ou `.gitkeep`.

`tests/` est interne au développement et ne doit jamais entrer dans les artefacts livrés.

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
- cache runtime livré
- duplication du framework dans les packages optionnels ou les sites
- tests dans les artefacts livrés

## Documentation

Chaque API publique doit être documentée façon Doxygen/phpDocumentor afin de générer les Reference Books.

NO DOC CONTRACT, NO PATCH.
