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

## Clean deliverable rule

Packages must not contain active Twig templates, legacy backups, dead CSS overrides, hidden fallbacks, runtime caches, secrets, vendor dumps or duplicated framework code.

Git history is the archive. The active package tree must stay clean and deliverable.
