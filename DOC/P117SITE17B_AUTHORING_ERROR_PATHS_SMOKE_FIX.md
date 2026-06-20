# P117SITE17B — Authoring error-path smoke fix

P117SITE17 failed on Windows because the invalid module-id smoke used `blog` while a valid `Blog` module already existed. Windows path lookup is case-insensitive, so the smoke resolved `blog` to the existing `Blog` directory.

This patch rewrites the smoke with an invalid module id that cannot collide by case with an existing module, and keeps all no-partial-write checks.

Expected marker: `P117SITE17B_AUTHORING_ERROR_PATHS_SMOKE_FIX_OK`.
