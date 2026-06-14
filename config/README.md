# OPUS configuration

This directory contains non-secret OPUS configuration templates and global development defaults.

## Contract

```text
config/ may be present in development and delivery trees.
config/ must not contain secrets.
config/ may contain example files only.
```

Runtime-specific configuration must be copied from templates and adapted locally.

Forbidden in this directory:

```text
.env
.env.local
secrets.json
secret.json
passwords
private keys
production tokens
```

## Delivery rule

Deliver `config/` with safe templates such as `opus.example.json`.
Never deliver machine-local secrets or private configuration.
