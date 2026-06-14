# OPUS var

This directory is reserved for runtime-generated local data.

## Contract

```text
var/ is useful in delivered OPUS distributions.
var/cache, var/logs and var/tmp may be delivered as empty folders.
Runtime-generated contents must not be committed or shipped.
```

Allowed in delivery:

```text
var/README.md
var/cache/.gitkeep
var/logs/.gitkeep
var/tmp/.gitkeep
```

Forbidden in delivery:

```text
var/cache/* runtime cache payloads
var/logs/* runtime log files
var/tmp/* temporary files
```

The folder exists to make the expected runtime topology readable at first glance.
