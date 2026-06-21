# P117SITE21 - create:site alias of create:application

## Status

Delivered.

## Contract

create:application is the canonical OPUS command for creating a fullstack application scaffold.

create:site is a backward-compatible alias of create:application.

A site is a public/application type, not a distinct legacy scaffold architecture.

## Rules

- create:site must generate the same fullstack frontend/backend separated scaffold as create:application.
- create:site must not use SiteScaffoldPlan or any legacy application root.
- create:site must not generate CMS/blog-oriented defaults.
- frontend/ is representation only.
- backend/ is business/data processing only.
- backoffice is never a synonym for backend.

## Runtime smoke

Run: python tools/smoke_p117site21_create_site_application_alias.py

Expected markers:

CHECK_CREATE_SITE_ALIAS_COMMAND=OK
CHECK_CREATE_SITE_ALIAS_FULLSTACK_STRUCTURE=OK
CHECK_CREATE_SITE_ALIAS_FRONTEND_BACKEND_SEPARATION=OK
CHECK_CREATE_SITE_ALIAS_NO_LEGACY_APPLICATION_ROOT=OK
P117SITE21_CREATE_SITE_APPLICATION_ALIAS_SMOKE_OK
CHECK_CLEANUP=OK
