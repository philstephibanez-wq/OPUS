# OPUS_USER_GUIDE package

OPUS_USER_GUIDE is the future optional user guide package for OPUS.

## Status

```text
Reserved package skeleton.
Implementation pending.
```

## Purpose

The User Guide is not the RefBook.

```text
RefBook     = technical reference for OPUS APIs, contracts and symbols.
User Guide  = human guide for installation, workflows, examples and day-to-day usage.
```

## Runtime rule

This package must use the shared OPUS core. It must not embed or duplicate `framework/Opus/`.

## Clean deliverable rule

No active Twig, no legacy backups, no dead CSS overrides, no hidden fallback, no runtime cache, no secret, no duplicated framework code.
