# P117W R45B2A4BS — OWASYS Source/Git lazy runtime

Baseline OPUS master: 7038d0264e90b4bb83f124fa752f834ae5ee792d

Scope:
- sites/owasys-front/application/source/controllers/SourceController.php
- sites/owasys-front/application/source/templates/index.score

Root cause corrected:
The Sources/Git server-rendered page eagerly executed the secured REST Git status,
history and selected-file diff on every ordinary source GET. Those Git calls are
now opt-in through the explicit `git=1` query and the SCORE UI exposes a Git load
action. Source browsing/editing therefore no longer pays the Git REST/Composer/Git
path unless the user actually requests Git data.

No OWASYS backend file changes.
No JavaScript changes.
No FSM/menu semantics changes.
No source/Git REST contract changes.
No generated application changes.
A4BR fresh-generation acceptance remains a separate pending gate.

Apply:
Extract this ZIP directly over H:\OPUS.

Validation commands are provided in the response accompanying the ZIP.
