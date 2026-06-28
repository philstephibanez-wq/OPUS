# OPUS API REST SSO Security Core

## Milestone

`P7_API_REST_SSO_SECURITY_CORE`

## Contract

The OPUS API security core already exists through the `Opus\Api` dispatcher stack.

The dispatcher contract is intentionally data-driven:

- `ApiRouteRegistry` loads route contracts;
- `DevHeaderSsoAuthenticator` resolves the SSO identity from configured trusted headers;
- `ConfigAclPolicy` decides access through the official ACL contract;
- `ConfigFsmGuard` validates FSM flow/signal declarations;
- endpoints receive an already resolved route, identity and explicit context.

No endpoint should duplicate SSO, ACL or FSM security logic.

## Smoke

```cmd
cd /d H:\OPUS
php tools\smokes\smoke_p7_api_rest_sso_security_core.php
```

The smoke validates anonymous public access, anonymous denial, SSO scope grant, SSO scope denial, missing route, FSM grant and FSM denial.
