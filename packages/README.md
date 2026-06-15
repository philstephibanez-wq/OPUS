# OPUS official optional packages

This directory contains official optional OPUS packages.

## Contract

```text
One OPUS framework core.
Several optional OPUS-powered packages.
No framework duplication per package or per installed site.
Composer-managed client installation.
No silent fallback.
Clean deliverable roots.
```

The OPUS core remains in:

```text
framework/Opus/
```

Optional packages may provide sites, guides, resources or publishable frontends, but they must resolve the shared OPUS core through an explicit manifest/config contract.

Development tools, smoke tests, recipes and generators belong to MAESTRO_WORKSPACE, not to package sources or installed client sites.

## Official packages

```text
packages/opus-8.1.0-lysenko-reference-book/  OPUS 8.1.0 "Lysenko" Reference Book
packages/opus-user-guide/                    future optional OPUS User Guide package
```

## Installed site target

A package source is not the same thing as an installed site.

Recommended topology:

```text
packages/opus-8.1.0-lysenko-reference-book/  package source
sites/opus-refbook/                          installed runtime site
```

The web server must expose only `sites/<site>/public/`.

## Manifest contract

Every official package must provide:

```text
opus-package.json
```

The manifest contract is documented in:

```text
packages/OPUS_PACKAGE_MANIFEST_CONTRACT.md
packages/opus-package.schema.json
```

## Install contract

Client deliverable package installation must be Composer-managed and multiplatform.

Packages must be installed without copying `framework/Opus/` into the target site.

The install contract is documented in:

```text
packages/OPUS_PACKAGE_INSTALL_CONTRACT.md
```

The client installation contract must not depend on OS-specific commands such as `xcopy`, `rmdir`, `mklink`, CMD, PowerShell or Windows-only paths.

Composer may invoke portable OPUS PHP installer logic, Composer scripts or a Composer installer plugin.

The installer writes a local `opus-runtime.local.json` file in the target package directory. This file declares the shared core path and keeps `fallback_allowed=false`.

## License inheritance

Official packages inherit the OPUS license intent unless their own manifest declares a stricter profile:

```text
license_profile = OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES
copyright_holder = Philippe Stéphane Ibanez
commercial_use = commercial license + royalties required
```

The license intent is documented at repository root in `LICENSE_INTENT.md`.

## Validation

Package and layout validators are development tools. They live in MAESTRO_WORKSPACE and are not part of client package installation.

A validation failure means the package tree or delivery tree is not clean enough to deliver.

## Clean deliverable rule

Packages must not contain active Twig templates, legacy backups, dead CSS overrides, hidden fallbacks, runtime caches, secrets, vendor dumps, duplicated framework code, DOC folders, tools folders, patch notes, TODO files or smoke scripts.

Git history and MAESTRO_WORKSPACE archives preserve history. The active package tree must stay clean and deliverable.
