# OPUS LSTSAR Manager package core

`P7_LSTSAR_MANAGER_PACKAGE_CORE` creates the first OPUS backoffice application package dedicated to declaring model-driven ODBC LSTSAR configurations.

## Purpose

The package is not a raw SQL console and not a DDL manager. It is a protected declarative backoffice for:

- ODBC source declarations;
- ODBC destination declarations;
- source model references;
- destination model references;
- source-to-destination mapping;
- Securize rules;
- Transform rules;
- Store policy;
- Archive policy;
- Report policy;
- dry-run previews.

## Package

```text
packages/opus-lstsar-manager/
```

Application slug:

```text
opus-lstsar-manager
```

Composer package:

```text
logandplay/opus-lstsar-manager
```

## Security

The application is protected, denied by default, and restricted to OPUS admin/developer roles.

Forbidden in this milestone:

- raw SQL routes;
- DDL routes;
- anonymous access;
- direct execute routes.

Dry-run is allowed and explicitly separated from future execution.

## Relationship with LSTSAR core

The manager uses the existing OPUS LSTSAR declaration contract:

```text
LSTSAR = Load / Securize / Transform / Store / Archive / Report
```

It prepares future configuration screens for `LstsarConfig` and `LstsarBackofficeDeclaration`.
