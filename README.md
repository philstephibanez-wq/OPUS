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
framework/Opus/                              core framework partagé unique
packages/                                    packages officiels OPUS installables
packages/opus-8.1.0-lysenko-reference-book/  site RefBook officiel versionné
packages/opus-user-guide/                    futur guide utilisateur optionnel
sites/                                       sites installés / instances runtime
config/                                      templates de configuration non secrets
var/                                         cache/logs/tmp locaux, livrés vides
```

Règle : un seul framework OPUS, plusieurs sites/packages OPUS, aucune duplication du core dans les packages ou les sites.

Un serveur web doit exposer uniquement les dossiers `sites/*/public/`, jamais `framework/`, `packages/`, `config/`, `var/` ou la racine OPUS.

## Workspace-only development context

Tests, smoke scripts, recettes, générateurs, outils de patch, rapports et racines legacy appartiennent à MAESTRO_WORKSPACE, pas à la racine produit OPUS visible ni aux livrables client.

Les commandes système locales sont autorisées pour le développement uniquement depuis MAESTRO_WORKSPACE. Elles ne font pas partie du contrat d’installation client.

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

## Installation des livrables client

L’installation d’un package OPUS destiné au client est Composer-managed et multiplateforme.

Elle ne doit pas dépendre de commandes système comme `xcopy`, `rmdir`, `mklink`, CMD, PowerShell ou de chemins Windows spécifiques.

Composer peut invoquer une logique OPUS PHP portable, un Composer script ou un Composer installer plugin. Cette logique doit résoudre explicitement le core OPUS partagé, écrire le contrat runtime local et échouer sans fallback silencieux si le contrat n’est pas respecté.

L'installation écrit un `opus-runtime.local.json` dans le site cible. Ce fichier pointe explicitement vers le core OPUS partagé et garde `fallback_allowed=false`.

## Validation développement

Les validateurs et recettes de développement vivent dans MAESTRO_WORKSPACE.

Validation packages :

```text
H:/MAESTRO_WORKSPACE/20_TECHNICAL_FOUNDATIONS/OPUS/tools/validate_opus_packages.php
```

Validation layout :

```text
H:/MAESTRO_WORKSPACE/20_TECHNICAL_FOUNDATIONS/OPUS/tools/validate_opus_delivery_layout.php
```

Ces chemins sont des chemins de développement local, pas un contrat d’installation client.

## Livraison

Une livraison OPUS core doit conserver l'arborescence utile (`sites/`, `packages/`, `config/`, `var/`) même si certains dossiers ne contiennent que des README ou `.gitkeep`.

`tests/`, recettes, smoke scripts, outils de patch, rapports et archives sont internes au développement et ne doivent jamais entrer dans les artefacts livrés.

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
- chemin absolu projet dans les livrables client
- fallback silencieux
- secret
- vendor committé dans les livrables client
- cache runtime livré
- duplication du framework dans les packages optionnels ou les sites
- tests dans les artefacts livrés

## Documentation

Chaque API publique doit être documentée façon Doxygen/phpDocumentor afin de générer les Reference Books.

NO DOC CONTRACT, NO PATCH.
