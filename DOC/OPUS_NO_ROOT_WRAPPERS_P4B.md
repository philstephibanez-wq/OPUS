# P4B - OPUS no root wrappers

## Decision

OPUS must not keep wrapper classes at the direct `Opus/` root.

Root files such as `Opus/Acl.php`, `Opus/Fsm.php`, `Opus/I18n.php`, and `Opus/Router.php` are not final architecture. They were introduced as a temporary reborn runtime layer and must be removed or moved into a real responsibility namespace.

## Rule

No root wrapper:

- no direct `Opus/Acl.php` wrapper;
- no direct `Opus/Fsm.php` wrapper;
- no direct `Opus/I18n.php` wrapper;
- no direct `Opus/Router.php` wrapper.

If the new runtime needs classes, they must be real classes in real folders, for example:

- `Opus/Kernel/Kernel.php`
- `Opus/Kernel/Acl.php`
- `Opus/Kernel/Fsm.php`
- `Opus/Kernel/I18n.php`
- `Opus/Kernel/Router.php`
- `Opus/Http/Request.php`
- `Opus/Http/Response.php`
- `Opus/Site/Package.php`
- `Opus/Site/PackageRepository.php`
- `Opus/Support/Support.php`
- `Opus/View/View.php`

## Historical classes

Historical ASAP/OPUS classes remain the source of truth until individually reviewed:

- `OPUS_Application`
- `OPUS_Router`
- `OPUS_Singleton`
- `OPUS_I18N_I18n`
- historical ACL/FSM classes.

## Next implementation step

Create a controlled migration for the temporary reborn runtime files, then rerun the validated smokes.

No deletion of historical classes is allowed in this step.
