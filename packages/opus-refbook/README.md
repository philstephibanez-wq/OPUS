# OPUS_REF_BOOK package

OPUS_REF_BOOK is the official optional RefBook site package for OPUS.

## Status

```text
Package skeleton created.
Clean import pending.
```

The old separate `OPUS_REF_BOOK` repository is transitional only. It must be audited before any import.

## Purpose

OPUS_REF_BOOK is a real OPUS-powered site, not a static markdown dump and not framework core code.

It must support:

```text
offline local consultation
published online documentation
explicit GitHub update checks
shared OPUS core runtime
```

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
