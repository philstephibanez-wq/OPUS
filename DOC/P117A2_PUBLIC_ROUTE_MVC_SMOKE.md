# P117A2 — OPUS Public Route MVC Smoke

Status: delivered
Date: 2026-06-16

## Purpose

P117A2 introduces the first minimal OPUS public route MVC smoke.

It validates that OPUS can demonstrate a public route without bypassing the internal control model:

```text
boot
-> public request
-> route declaration
-> public router resolution
-> public control plane decision
-> authorized public action
-> public page model
-> ScoreTemplate-compatible renderer
-> public response
-> runtime log event
```

## Public opacity rule

A blocked public request must not expose technical details.

The public blocked response remains:

```text
Site temporairement bloqué.
Contactez le support.
```

Internal details remain available only in protected diagnostics, logs, reports or the future administrator dashboard.

## Delivered classes

```text
framework/Opus/Http/PublicRequest.php
framework/Opus/Http/PublicResponse.php
framework/Opus/Routing/PublicRoute.php
framework/Opus/Routing/PublicRouter.php
framework/Opus/Security/PublicControlDecision.php
framework/Opus/Security/PublicRouteControlPlane.php
framework/Opus/Security/PublicBlockedResponseRenderer.php
framework/Opus/PublicSite/PublicPageModel.php
framework/Opus/PublicSite/PublicHomeAction.php
framework/Opus/Template/SimpleScoreTemplateRenderer.php
framework/Opus/Runtime/PublicRouteMvcSmoke.php
```

## Smoke command

```cmd
cd /d H:\OPUS
php -r "$boot=require 'index.php'; $r=\\Opus\\Runtime\\PublicRouteMvcSmoke::run(__DIR__); foreach (['ok','gate'] as $k) { echo $k.'='.(is_bool($r[$k]) ? ($r[$k] ? 'true' : 'false') : $r[$k]).PHP_EOL; } echo 'normal_status='.$r['normal_public_response']['status'].PHP_EOL; echo 'blocked_status='.$r['blocked_public_response']['status'].PHP_EOL; echo 'blocked_body='.str_replace(PHP_EOL, ' | ', $r['blocked_public_response']['body']).PHP_EOL;"
```

## Expected result

```text
ok=true
gate=P117A2_OPUS_PUBLIC_ROUTE_MVC_SMOKE
normal_status=200
blocked_status=503
blocked_body=Site temporairement bloqué. | Contactez le support.
```
