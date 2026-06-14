# OPUS Package Install Contract

Status: active architecture contract.

## Purpose

This document defines how an official optional OPUS package is installed for local or published use.

The installer contract exists to preserve the original OPUS design goal:

```text
One OPUS framework core.
Several OPUS-powered packages/sites.
No duplicated framework per site.
```

## Mandatory install model

An optional package installation must:

```text
- copy or deploy the package/site files only;
- resolve an existing shared OPUS core explicitly;
- install into the OPUS sites/ topology by default or by explicit target;
- write an explicit local runtime contract;
- fail if the OPUS core is missing;
- fail if a target path is unsafe or non-empty;
- fail if package scories are detected;
- never fallback to an embedded or guessed framework.
```

## Recommended local topology

For a readable local UwAmp/dev installation, OPUS may live at:

```text
H:\UwAmp\www\OPUS\
```

Installed sites should live under:

```text
H:\UwAmp\www\OPUS\sites\
```

Example:

```text
H:\UwAmp\www\OPUS\sites\opus-refbook\
```

The web server must expose only the installed site's `public/` directory, not the OPUS root.

## Forbidden model

This layout is forbidden in an installed site/package:

```text
OPUS_REF_BOOK/
  framework/Opus/
```

A package may not ship its own copy of `framework/Opus/`.

## Runtime contract file

The installer writes this local file in the target directory:

```text
opus-runtime.local.json
```

This file is not a source package manifest. It is local installation state. It must declare:

```text
runtime_contract = OPUS_SHARED_CORE_PACKAGE_RUNTIME
package_slug
package_name
installed_from
opus_framework
fallback_allowed = false
framework_duplication_allowed = false
created_at_utc
```

## Official installer

The official installer tool is:

```text
tools/install_opus_package.php
```

Example dry run:

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS\sites\opus-refbook --opus-root=H:\UwAmp\www\OPUS --dry-run
```

Example install:

```text
php tools/install_opus_package.php --package=opus-refbook --target=H:\UwAmp\www\OPUS\sites\opus-refbook --opus-root=H:\UwAmp\www\OPUS
```

## License inheritance

Installed packages remain governed by the OPUS license intent unless a stricter package manifest is defined.

Required profile:

```text
OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES
```

Required holder:

```text
Philippe Stéphane Ibanez
```

Commercial use requires a paid commercial license and royalties.

## Delivery gate

Before installing or packaging an optional package, run:

```text
php tools/validate_opus_packages.php
php tools/validate_opus_delivery_layout.php --root=H:\UwAmp\www\OPUS --mode=dev
```

Before shipping a delivery artifact, run the delivery layout validator against the artifact root:

```text
php tools/validate_opus_delivery_layout.php --root=<DELIVERY_ROOT> --mode=delivery
```

Validation failure means the package or delivery tree is not clean enough to deliver or install.
