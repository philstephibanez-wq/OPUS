# OPUS official optional packages

This directory contains official optional OPUS packages.

## Contract

```text
One OPUS framework core.
Several optional OPUS-powered packages.
No framework duplication per package.
```

The OPUS core remains in:

```text
framework/Opus/
```

Optional packages may provide sites, guides, tools, resources or publishable frontends, but they must resolve the shared OPUS core through an explicit manifest/config contract.

## Official packages

```text
packages/opus-refbook/       official optional OPUS RefBook site package
packages/opus-user-guide/    future optional OPUS User Guide package
```

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

Packages must be installed without copying `framework/Opus/` into the target site.

The install contract is documented in:

```text
packages/OPUS_PACKAGE_INSTALL_CONTRACT.md
```

Official installer:

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS_REF_BOOK --opus-root=H:\OPUS --dry-run
```

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

Package gates can be checked with:

```text
php tools/validate_opus_packages.php
```

The validator is a maintenance tool. It does not modify files. A failure means the package tree is not clean enough to deliver.

## Clean deliverable rule

Packages must not contain active Twig templates, legacy backups, dead CSS overrides, hidden fallbacks, runtime caches, secrets, vendor dumps or duplicated framework code.

Git history is the archive. The active package tree must stay clean and deliverable.
