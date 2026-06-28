# OPUS LSTSAR API Integration Core

## Milestone

`P7_LSTSAR_API_INTEGRATION_CORE`

## Contract

This milestone connects LSTSAR to the existing OPUS API dispatcher.

The API dispatcher remains responsible for:

- route resolution;
- trusted SSO identity resolution;
- ACL policy decision;
- FSM guard decision.

LSTSAR endpoints receive the already computed `AccessDecisionInterface` through the dispatcher context. They do not duplicate SSO, role, scope or FSM logic.

## Added endpoints

- `Opus\Api\Endpoint\LstsarProcessEndpoint`
- `Opus\Api\Endpoint\LstsarRestoreEndpoint`

## Storage

`Opus\Lstsar\JsonFileLstsarStore` persists records and audit events under:

```text
<project-root>/var/lstsar/<dataset>
```

## Route metadata

A processing route must define:

```json
{
  "lstsar": {
    "dataset": "orders",
    "schema": "orders"
  }
}
```

The schema is loaded from:

```text
<project-root>/config/lstsar/orders.json
```

## Smoke

```cmd
cd /d H:\OPUS
php tools\smokes\smoke_p7_lstsar_api_integration_core.php
```
