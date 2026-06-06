# P112Q2I0 â€” ASAP GitHub Bootstrap

## Role
Prepare the ASAP framework repository before the Lstsa chantier.

## Decision
ASAP must be placed under a clean private GitHub repository before the Lstsa engine is implemented.

Target repository name:

```text
philstephibanez-wq/ASAP
```

The local source of truth remains:

```text
H:\ASAP
```

## Contract
- GitHub is the remote source of truth after the first successful push.
- The repository must stay private unless explicitly changed by the owner.
- Generated runtime data, local secrets, logs, cache, temporary files and Lstsa run outputs must not be committed.
- The first remote branch follows the current local branch, normally `master`.
- `ASAP_REF_BOOK` is not silently merged into this repository. It remains a separate target unless a later palier explicitly decides otherwise.

## Lstsa consequence
The next chantier is:

```text
P112Q2I1_ASAP_SITE_MULTI_DB_AND_Lstsa_CONTRACT
```

No Lstsa code should be added before the GitHub bootstrap has been validated.

## Official scripts
- `tools/automation/p112q2i0_asap_github_bootstrap_check.cmd`
- `tools/automation/p112q2i0_asap_github_push_origin.cmd`

## Validation
The bootstrap is valid when:

```text
P112Q2I0_TEST_OK
```

The remote publication is valid when:

```text
P112Q2I0_GITHUB_PUSH_OK
```