# OPUS Package Install Contract

Status: active architecture contract.

## Purpose

This document defines how an official optional OPUS package is installed for client delivery, local use, published use and development validation.

The installer contract preserves the OPUS design goal:

```text
One OPUS framework core.
Several OPUS-powered packages/sites.
No duplicated framework per site.
No silent fallback.
Clean deliverable roots.
```

## Client deliverable install model

Client deliverable package installation must be Composer-managed and multiplatform.

A client package installation contract must not depend on:

```text
xcopy
rmdir
mklink
cmd
PowerShell
shell-specific scripts
OS-specific filesystem commands
```

Composer may call OPUS PHP installer logic, Composer scripts or a Composer installer plugin, but that installer logic must use portable PHP APIs and explicit contracts.

## Development model

Development tooling may use local workspace commands, CMD recipes, smoke tests, generators and auditors.

These tools must execute from MAESTRO_WORKSPACE and must not be stored or executed from client deliverable roots, package roots, public site roots or legacy site roots.

## Mandatory install behavior

An optional package installation must:

```text
- install package/site files through Composer-managed logic;
- resolve an existing shared OPUS core explicitly;
- install into the OPUS sites topology by default or by explicit target;
- write an explicit local runtime contract;
- fail if the OPUS core is missing;
- fail if a target path is unsafe;
- fail if a target path is non-empty unless Composer/OPUS explicitly owns it;
- fail if package scories are detected;
- never fallback to an embedded or guessed framework;
- never duplicate framework/Opus in the installed package.
```

## Recommended topology

OPUS core remains shared.

Installed sites should live under the OPUS sites topology:

```text
<OPUS_ROOT>/sites/<package-site>/
```

The web server must expose only the installed site's `public/` directory, not the OPUS root.

## Forbidden installed site/package layout

This layout is forbidden:

```text
<INSTALLED_SITE>/
  framework/Opus/
```

A package may not ship its own copy of `framework/Opus/`.

## Runtime contract file

The installer writes this local file in the target directory:

```text
opus-runtime.local.json
```

This file is local installation state. It is not the source package manifest.

It must declare:

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

## Official installer direction

The official client-facing installation mechanism is Composer.

The future OPUS Composer installer must be implemented as portable PHP code under the OPUS namespace and invoked by Composer, not by OS-specific shell commands.

Workspace recipes may validate the installer during development, but they are not the client installation contract.

## License inheritance

Installed packages remain governed by the OPUS license intent unless a stricter package manifest is defined.

Required profile:

```text
OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES
```

Commercial use requires a paid commercial license and royalties.

## Delivery gate

Before packaging or delivering an optional package, validators must prove the active tree is clean.

Validation failure means the package or delivery tree is not clean enough to deliver or install.
