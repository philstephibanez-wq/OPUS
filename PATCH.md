# PATCH P7A0J_CLEAN_CLONE_I18N_SMTP_GATES

## Scope

Adds an executable clean-clone validation gate for the already committed `P7A0I_I18N_SMTP_CONTRACT` milestone.

## Files

- `tools/smokes/smoke_p7a0j_clean_clone_i18n_smtp_gates.py`
- `RUN_P7A0J_CLEAN_CLONE_I18N_SMTP_GATES.cmd`
- `PATCH.md`
- `CHANGELOG.md`
- `TODO.md`

## Behavior

The smoke:

1. resolves the current Git repository root;
2. validates required P7A0I contract and handoff markers;
3. checks tracked root hygiene for accidental artifacts;
4. creates a temporary clean clone from the current committed HEAD;
5. runs the existing `P7A0I` smoke inside that clean clone;
6. runs the same `P7A0I` smoke in the current source tree;
7. deletes its temporary clone directory.

The working tree may be dirty while validating this patch. This is intentional so the runner can be executed immediately after extraction and before commit.

## Run

```cmd
RUN_P7A0J_CLEAN_CLONE_I18N_SMTP_GATES.cmd
```
