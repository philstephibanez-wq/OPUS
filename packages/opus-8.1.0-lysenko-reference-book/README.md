# OPUS 8.1.0 "Lysenko" Reference Book package

This package is the official versioned Reference Book package for OPUS 8.1.0 "Lysenko".

## Identity

```text
Display name: OPUS 8.1.0 "Lysenko" Reference Book
Package slug: opus-8.1.0-lysenko-reference-book
Target OPUS version: 8.1.0
Target OPUS codename: Lysenko
```

## Status

```text
Package skeleton created.
Clean import pending.
```

The old separate `OPUS_REF_BOOK` repository is transitional only. It must be audited before any import.

## Purpose

The Reference Book is a real OPUS-powered site package, not a static markdown dump and not framework core code.

It must support:

```text
offline local consultation
published online documentation
explicit GitHub update checks
shared OPUS core runtime
```

## Version rule

Each OPUS release owns its own Reference Book package.

```text
OPUS 8.1.0 "Lysenko" Reference Book
-> packages/opus-8.1.0-lysenko-reference-book
```

The active site may keep a stable URL such as `sites/opus-refbook`, but it must display the exact OPUS version and codename it documents.

## Import gate

No file may be imported from the transitional repository until the source is audited for:

```text
0 active Twig templates
0 legacy backups
0 dead CSS overrides
0 hidden fallbacks
0 runtime caches
0 duplicated OPUS framework code
0 broken public i18n route
```

## Runtime rule

This package must not embed `framework/Opus/`. It must resolve the shared OPUS core through `opus-package.json` and official bootstrap configuration.
