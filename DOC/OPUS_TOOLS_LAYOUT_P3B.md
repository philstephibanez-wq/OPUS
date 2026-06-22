# OPUS tools layout — P3B

## Goal

Clean the root of `tools/` without touching OPUS runtime code.

The OPUS framework root had accumulated one-shot migration scripts and smoke tests directly under `tools/`. This made the repository harder to read.

## Decision

Use explicit tool folders:

```text
tools/audits/
tools/migrations/
tools/smokes/
```

## Kept as active tools

```text
tools/audits/audit_opus_root_cleanup_p3.py
tools/migrations/apply_opus_tools_layout_p3b.py
tools/smokes/smoke_opus_boot_render_p1.php
tools/smokes/smoke_opus_view_scoretemplate_p1b.php
tools/smokes/smoke_opus_naming_p1d.py
tools/smokes/smoke_opus_singleton_accessor_p2.php
tools/smokes/smoke_opus_tools_layout_p3b.py
```

## Removed as obsolete after committed milestones

```text
tools/opus_reborn_cleanup_p0.py
tools/smoke_opus_reborn_cleanup_p0.py
tools/audit_opus_naming_p1c.py
tools/apply_opus_naming_p1d.py
tools/apply_opus_singleton_accessor_p2.py
```

These scripts were one-shot migration helpers. Their results are now committed and covered by current smoke tests.

## Runtime safety

P3B does not change:

```text
Opus/
www/
composer.json
sites/
vendor/
var/
```

## Validation

Run:

```cmd
python tools\migrations\apply_opus_tools_layout_p3b.py
python tools\migrations\apply_opus_tools_layout_p3b.py --write
python tools\smokes\smoke_opus_tools_layout_p3b.py
php tools\smokes\smoke_opus_boot_render_p1.php
php tools\smokes\smoke_opus_view_scoretemplate_p1b.php
python tools\smokes\smoke_opus_naming_p1d.py
php tools\smokes\smoke_opus_singleton_accessor_p2.php
```

Expected:

```text
P3B_OPUS_TOOLS_LAYOUT_APPLY_OK
P3B_OPUS_TOOLS_LAYOUT_SMOKE_OK
P1_OPUS_BOOT_RENDER_SMOKE_OK
P1B_OPUS_VIEW_SCORETEMPLATE_SMOKE_OK
P1D_OPUS_NAMING_SMOKE_OK
P2_OPUS_SINGLETON_ACCESSOR_SMOKE_OK
```
