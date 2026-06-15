# OPUS Package Manifest Contract

Status: active architecture contract.

## Purpose

Every official optional OPUS package must declare its runtime, delivery, clean-gate and license contract in `opus-package.json`.

The manifest is not decorative metadata. It is the package contract used by maintainers, packaging tools, Composer-managed installers and future delivery validators.

## Required principles

```text
One shared OPUS core.
Several optional OPUS packages.
No framework duplication inside a package.
No silent fallback.
No active legacy artifacts in deliverables.
Composer-managed client installation.
Clean product roots.
```

## Required package fields

```text
package_name
package_slug
package_type
package_status
requires_opus_version
requires_opus_codename
requires_opus_name
entrypoint
public_root
application_root
resources
core_resolution
license_profile
license
publication_profile
clean_deliverable_gate
```

## Core resolution contract

A package must declare:

```text
mode = shared_opus_core_required
forbid_embedded_framework = true
fail_if_core_missing = true
fallback_allowed = false
```

A package must never ship its own `framework/Opus/` copy.

## Install contract

Client deliverable installation is Composer-managed and multiplatform.

A package manifest must not imply that client installation depends on OS-specific commands such as `xcopy`, `rmdir`, `mklink`, CMD or PowerShell.

Development recipes may exist only in MAESTRO_WORKSPACE and must not be part of client deliverables.

## License contract

Official OPUS packages inherit the OPUS license intent unless a stricter manifest is explicitly declared.

Required license profile:

```text
OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES
```

Commercial use requires a paid commercial license and royalties.

No manifest may advertise OSI open source status unless a future signed architecture decision changes the OPUS licensing model.

## Clean deliverable gate

A package manifest must reject these scories in active deliverables:

```text
active Twig templates
legacy backups
old/bak/orig/tmp files
runtime caches
secrets
vendor dumps
dead CSS overrides
duplicated framework code
hidden fallbacks
DOC folders
tools folders
patch notes
TODO files
smoke scripts
```

Git history and MAESTRO_WORKSPACE archives preserve history. The active deliverable tree must stay clean.

## Current official packages

```text
packages/opus-8.1.0-lysenko-reference-book/  OPUS 8.1.0 "Lysenko" Reference Book
packages/opus-user-guide/                    OPUS_USER_GUIDE optional future user guide
```
