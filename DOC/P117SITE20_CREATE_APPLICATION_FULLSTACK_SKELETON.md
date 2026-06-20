# P117SITE20 - Create application fullstack skeleton

Status: DELIVERED
Date: 2026-06-21
Scope: OPUS Composer authoring commands, generated application skeletons

## Goal

Implement the first neutral fullstack OPUS application generator based on the P117SITE19 contract.

A generated OPUS application is not a pile of pages and not a blog/CMS starter. It is a fullstack application with a mandatory and explicit separation:

- `frontend/` for representation.
- `backend/` for business/data processing.

## New command

```text
composer opus:create-application -- <application-id> --write
```

Dry-run remains the default:

```text
composer opus:create-application -- <application-id>
```

Optional dev server:

```text
composer opus:create-application -- <application-id> --write --serve --port 8791
```

## Generated canonical structure

```text
sites/<application_id>/
├── application.opus.json
├── public/
│   └── index.php
├── frontend/
│   ├── views/
│   ├── layouts/
│   ├── sections/
│   ├── custom-components/
│   ├── navigation/
│   ├── api-clients/
│   ├── assets/
│   └── theme/
├── backend/
│   ├── modules/
│   ├── services/
│   ├── actions/
│   ├── repositories/
│   ├── validators/
│   ├── policies/
│   ├── api-endpoints/
│   ├── runners/
│   ├── jobs/
│   ├── dto/
│   └── viewmodels/
├── resources/
│   └── i18n/
└── docs/
```

## Important decisions

- `View` is the frontend term. `Page` is not the core OPUS vocabulary.
- `Form` is a standard OPUS component, not a top-level architecture root.
- `Menu` is a standard OPUS component, not a top-level architecture root.
- The application owns navigation data/configuration and optional custom components.
- OPUS owns the standard component library.
- Backend is not backoffice.
- Backoffice is a possible frontend specialization consuming backend APIs.
- Backend modules are business/data domains, not frontend view names.
- The generator must not create mandatory `Articles`, `Rubriques`, `Blog` or `News` concepts.

## Validation

Smoke command:

```text
python tools/smoke_p117site20_create_application_fullstack_skeleton.py
```

The smoke creates a temporary `sites/p117site20-smoke`, validates the contract, then deletes it.
