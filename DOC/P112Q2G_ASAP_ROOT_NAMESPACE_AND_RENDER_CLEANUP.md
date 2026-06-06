# P112Q2G — ASAP Root Namespace and Render Cleanup

## Purpose

P112Q2G cleans the final framework layout issue reported by P112Q2F.

## Decisions

- `framework/Asap/*.php` is no longer accepted.
- Root compatibility classes move into explicit domains.
- The decorative `Render` directory is removed.
- `Renderer` remains the official rendering domain.
- No fallback root files are kept.

## Main moves

- `Acl.php` -> `Acl/Acl.php`
- `Bootstrap.php` -> `Core/Bootstrap.php`
- `Kernel.php` -> `Core/Kernel.php`
- `Configuration.php` -> `Config/Configuration.php`
- `ConfigLoader.php` -> `Config/ConfigLoader.php`
- `Exception.php` -> `Exception/Exception.php`
- `Package.php` -> `Package/Package.php`
- `PackageRepository.php` -> `Package/PackageRepository.php`
- `Response.php` -> `Response/ResponseFacade.php`
- `Support.php` -> `Support/Support.php`
- `Validator.php` -> `Validation/Validator.php`
- `SimpleXMLElementExtended.php` -> `Compatibility/SimpleXMLElementExtended.php`
- `Singleton.php` -> `Compatibility/Singleton.php`
- legacy `.class.php` files -> explicit `Compatibility/Legacy*.php`

## Contract

- No root PHP file remains under `framework/Asap`.
- No `Render` directory remains.
- No hidden fallback path is created.
- Prior naming recipes are rerun as regression checks.

## Runner

`H:\ASAP\tools\automation\p112q2g_root_namespace_and_render_cleanup_recipe_runner.cmd`
