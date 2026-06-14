# OPUS sites

This directory is reserved for installed OPUS-powered sites and local site instances.

## Contract

```text
sites/ is user-facing runtime topology.
packages/ is official optional package source.
framework/Opus/ is the single shared core.
```

A site installed here must not embed a copy of `framework/Opus/`.

Each installed site should provide or receive a local runtime contract file:

```text
opus-runtime.local.json
```

That file must point to the shared OPUS core and keep:

```text
fallback_allowed = false
framework_duplication_allowed = false
```

## Typical local layout

```text
sites/
  opus-refbook/
    public/
    application/
    resources/
    opus-runtime.local.json

  demo/
    public/
    application/
    resources/
    opus-runtime.local.json
```

## Delivery rule

This directory is useful in delivered OPUS distributions, even when it only contains this README.
It explains where user sites and optional package installations belong.

tests/ is different: tests are development-only and must not be shipped in delivery artifacts.
