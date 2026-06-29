# OPUS Application Package Contract Core

Milestone: `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`.

## Contract

Official OPUS applications are Composer-installable packages.

This applies to:

- OPUS RefBook;
- OPUS demo applications;
- OPUS ODBC Manager / ODBC Explorer;
- OPUS User Guide;
- future OPUS-owned site applications.

Manual folder copy is not the official installation contract.

## Package requirements

Each application package must provide:

- `composer.json`;
- Composer type `opus-application`;
- PSR-4 autoloading;
- an OPUS application manifest, by default `opus.application.json`;
- application paths for routes, views and I18N;
- security metadata for protected applications;
- smoke evidence that the package contract is valid and discoverable.

## Manifest contract

The manifest contract is:

```text
OPUS_APPLICATION_PACKAGE_MANIFEST_V1
```

Minimum manifest keys:

```json
{
  "contract": "OPUS_APPLICATION_PACKAGE_MANIFEST_V1",
  "package": "logandplay/opus-odbc-manager",
  "application": {
    "slug": "opus-odbc-manager",
    "name": "OPUS ODBC Manager"
  },
  "paths": {
    "application": "app",
    "routes": "app/routes.php",
    "views": "templates",
    "i18n": "i18n"
  },
  "integrations": {
    "scoretemplate": true,
    "i18n": true,
    "sso_acl": true,
    "diagnostics": true,
    "profiler": true
  },
  "security": {
    "protected": true
  }
}
```

## Immediate impact

`P7_ODBC_EXPLORER_SITE_APP_CORE` must not create a non-contractual loose application. The ODBC Manager site must be package-first and Composer-installable.
