# P1C — OPUS naming audit

## Status

Read-only audit stage.

No runtime refactor is performed by this palier.

## Goal

OPUS reborn must keep a coherent framework naming model before any site extraction or deeper runtime change.

Current known inconsistencies include uppercase legacy folders and class segments:

```text
Opus/VIEW
Opus/URL
Opus/SMTP
OPUS_VIEW_*
OPUS_URL_*
OPUS_SMTP_*
```

## Decision: URL is not a component

`Url` is not a UI component.

A `Link` component may render an anchor or link object for UI/navigation.

A `Url` object is a lower-level URL value/resolver used by routing, menu/link generation and request handling.

Therefore the target is:

```text
Opus/Url/Url.class.php
OPUS_Url_Url
```

Not:

```text
Opus/Componants/Url
```

## Proposed naming targets

```text
Opus/VIEW/Html.class.php  -> Opus/Html/Html.class.php
OPUS_VIEW_Html            -> OPUS_Html_Html

Opus/URL/Url.class.php    -> Opus/Url/Url.class.php
OPUS_URL_Url              -> OPUS_Url_Url

Opus/SMTP/                -> Opus/Smtp/
OPUS_SMTP_*               -> REVIEW before class rename
```

`Opus/View.php` remains the high-level HTML rendering facade and must not be confused with the legacy `VIEW` folder.

## Kernel and Package review

`Kernel`, `Package`, and `PackageRepository` remain in review.

They are not changed by P1C.

Current understanding:

```text
Kernel            = modern runtime coordinator, possibly redundant with OPUS_Application
Package           = site/application package descriptor, not Composer package
PackageRepository = package catalog/resolver, currently tied too strongly to local sites/
```

Those classes should be evaluated after naming is stable.

## Local audit command

```cmd
cd /d H:\OPUS
git pull --ff-only
python tools\audit_opus_naming_p1c.py
git status --short
```

Expected result:

```text
P1C_OPUS_NAMING_AUDIT_OK
```

## Rule

Audit first. Rename second. No broad refactor.
