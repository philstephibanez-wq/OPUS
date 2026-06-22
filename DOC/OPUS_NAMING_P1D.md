# P1D — OPUS naming standardization

## Status

Delivered as controlled tooling only.

## Goal

Normalize remaining uppercase legacy technical segments in the OPUS framework:

- `Opus/VIEW` -> `Opus/Html`
- `OPUS_VIEW_Html` -> `OPUS_Html_Html`
- `Opus/URL` -> `Opus/Url`
- `OPUS_URL_Url` -> `OPUS_Url_Url`
- `Opus/SMTP` -> `Opus/Smtp`

## Decision: Url is not a component

`Url` is a technical value/resolver used by Link, Menu and Router. It is not a visual/UI component.

Therefore:

- `Opus/Componants/Link` stays a component.
- `Opus/Url` becomes the technical URL layer.

## Decision: View facade vs HTML implementation

`Opus/View.php` is the high-level rendering facade and must stay named `View.php`.

The historical `Opus/VIEW/Html.class.php` is the concrete HTML view implementation, so it is renamed to:

- `Opus/Html/Html.class.php`
- `OPUS_Html_Html`

## Decision: SMTP

`SMTP` is infrastructure / mail transport, not a UI component.

Only the directory casing is normalized in this pass:

- `Opus/SMTP` -> `Opus/Smtp`

The internal `SMTP4PHP` namespace must not be rewritten blindly.

## Scope

Allowed:

- `Opus/`
- `www/`
- `composer.json`
- `README.md`

Forbidden:

- `sites/`
- `vendor/`
- `var/cache/`
- `var/log/`
- `var/tmp/`

## Commands

Preview:

```cmd
cd /d H:\OPUS
python tools\apply_opus_naming_p1d.py
```

Apply:

```cmd
cd /d H:\OPUS
python tools\apply_opus_naming_p1d.py --write
python tools\smoke_opus_naming_p1d.py
php tools\smoke_opus_boot_render_p1.php
php tools\smoke_opus_view_scoretemplate_p1b.php
git status --short
```

Expected:

```text
P1D_OPUS_NAMING_APPLY_OK
P1D_OPUS_NAMING_SMOKE_OK
P1_OPUS_BOOT_RENDER_SMOKE_OK
P1B_OPUS_VIEW_SCORETEMPLATE_SMOKE_OK
```

## Contract

This step is naming-only.

It must not:

- rewrite framework architecture;
- alter FSM behavior;
- alter Router behavior;
- move sites out of the repository;
- touch application site code;
- touch vendor or runtime cache.
