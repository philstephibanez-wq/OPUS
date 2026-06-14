# OPUS Delivery Profile

Status: active delivery contract.

## Purpose

This document defines what belongs in an OPUS delivery artifact and what stays development-only.

The goal is a readable, useful tree at first glance without shipping development scories.

## Mandatory delivered topology

A clean OPUS core delivery should keep these useful roots:

```text
OPUS/
  framework/Opus/
  packages/
  sites/
  tools/
  config/
  var/
  LICENSE_INTENT.md
  README.md
```

`sites/`, `packages/`, `config/` and `var/` may be delivered with README files and empty placeholder directories when no runtime content is installed yet.

## Development-only exclusions

The following must not be shipped in delivery artifacts:

```text
tests/
.git/
.github/
coverage/
reports/
node_modules/
var/cache/* runtime payloads
var/logs/* runtime log files
var/tmp/* temporary files
*.bak
*.old
*.orig
*.tmp
*.swp
*_legacy*
.env
.env.local
secrets.json
secret.json
```

`tests/`, smoke scripts, recipes, reports and legacy roots belong to MAESTRO_WORKSPACE. They are not part of the visible OPUS product root or end-user distribution.

## Useful empty roots

Useful empty roots are allowed and expected in delivery:

```text
sites/README.md
packages/README.md
config/README.md
config/opus.example.json
var/README.md
var/cache/.gitkeep
var/logs/.gitkeep
var/tmp/.gitkeep
```

These roots explain where sites, packages, configuration templates and runtime output belong.

## Public exposure rule

If OPUS is placed under a web root such as `www/OPUS`, the web server must expose only site `public/` directories.

Forbidden direct public exposure:

```text
framework/
packages/
tools/
tests/
config/
var/
```

Allowed public exposure examples:

```text
OPUS/sites/opus-refbook/public/
OPUS/sites/demo/public/
OPUS/sites/logandplay/public/
```

## License inheritance

All delivered OPUS artifacts inherit the license intent unless a stricter package manifest is provided:

```text
OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES
Copyright © Philippe Stéphane Ibanez
Commercial use requires a paid commercial license and royalties.
```

## Validation

Use the delivery layout validator before packaging a delivery tree:

```text
php tools/validate_opus_delivery_layout.php --root=H:\UwAmp\www\OPUS --mode=delivery
```

Use development mode for the working repository:

```text
php tools/validate_opus_delivery_layout.php --root=H:\UwAmp\www\OPUS --mode=dev
```

Development mode accepts `tests/`; delivery mode rejects it.
