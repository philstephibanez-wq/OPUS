# OPUS LSTSAR Contract Core

## Milestone

`P7_LSTSAR_CONTRACT_CORE`

## Meaning

LSTSAR means:

- Load
- Secure
- Transform
- Store
- Audit
- Restore

## Contract

The OPUS LSTSAR core is data-driven and framework-level.

It does not duplicate API, SSO or ACL logic. Security is injected as an `AccessDecisionInterface` already computed by the OPUS security layer.

The engine validates two separate states:

- source received before transformation;
- target produced after transformation.

Both stages may define type, length, byte-size and numeric constraints.

## Supported constraints

- `type`
- `required`
- `min_length`
- `max_length`
- `exact_length`
- `max_bytes`
- `min`
- `max`
- `precision`
- `scale`

## Supported transformations

- trim
- uppercase
- lowercase
- cast
- right padding
- numeric rounding

## Smoke

```cmd
cd /d H:\OPUS
php tools\smokes\smoke_p7_lstsar_contract_core.php
```
