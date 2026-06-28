# P4B - OPUS no root wrappers

## Status

SUPERSEDED / CORRECTED by:

- `DOC/CONTRACTS/OPUS_FSM_FIRST_ENGINE_CONTRACT.md`

This document is kept only as historical context for the P4 discussion.

## Corrected decision

OPUS must not keep wrapper classes at the direct `Opus/` root.

More importantly: wrappers must not be moved elsewhere as a solution.

A wrapper that only relays to another real class must not exist.

Therefore this earlier idea is invalid:

- `Opus/Kernel/Acl.php`
- `Opus/Kernel/Fsm.php`
- `Opus/Kernel/I18n.php`
- `Opus/Kernel/Router.php`

Moving a wrapper does not make it clean.

## Mandatory FSM-first rule

The valid architecture is now defined by the FSM-first contract:

- `index.php` is the only public web entry point.
- FSM is the engine.
- Boot is driven by FSM.
- Runtime is driven by FSM.
- Transitions are configurable.
- Application/site singleton is the runtime anchor.
- Router translates request intent but never replaces FSM.
- No wrapper may be created, preserved, or moved as a solution.
- Kernel is not sovereign. If it duplicates Application or bypasses FSM, it must disappear.

## Historical classes remain source of truth

Historical OPUS classes remain the source of truth until individually reviewed:

- `OPUS_Application`
- `OPUS_Router`
- `OPUS_Singleton`
- `OPUS_I18N_I18n`
- historical ACL/FSM classes.

## Next implementation step

Do not migrate wrappers into other folders.

The next valid implementation step is:

1. Inspect the real historical classes.
2. Make `index.php` enter the FSM-first boot path.
3. Connect the application/site singleton.
4. Connect the real router and real FSM.
5. Delete wrapper classes only when their call sites have been replaced by real classes.

No deletion of historical classes is allowed in this step.
