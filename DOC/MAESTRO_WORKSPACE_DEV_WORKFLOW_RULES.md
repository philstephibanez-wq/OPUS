# MAESTRO WORKSPACE — Development Workflow Rules

## Documentation source rule

- WORKSPACE_RULE_MD_NEVER_REPLACE_SOURCE_TAGS=1

Markdown files are allowed and useful for:

- contracts
- handoffs
- decisions
- historical notes
- AI/context documentation
- human workflow documentation

Markdown files must never replace embedded source documentation tags when generating official technical documentation.

Official generated technical documentation must follow:

```text
source code + official documentation tags
        ↓
extractor
        ↓
structured manifest
        ↓
official renderer
        ↓
final documentation
```

## Git workflow rule

- WORKFLOW_APPLY_TEST_STAGE_COMMIT_PUSH=1
- WORKFLOW_STAGE_EXACT_AFTER_TEST_OK=1
- WORKFLOW_COMMIT_PUSH_AFTER_PHASE_VALIDATED=1

The accepted workflow is:

```text
APPLY
TEST
if tests are OK:
  git add exact files
  git commit
  git push
```

No broad staging.
No commit before test validation.
No silent fallback.