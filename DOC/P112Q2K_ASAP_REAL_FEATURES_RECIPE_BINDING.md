# P112Q2K — ASAP real features recipe binding

## Role

Bind the global ASAP recipe suite to the real historical `ASAP_REF_BOOK` application instead of validating only sandbox pages.

## Contract

`real feature binding` is a mandatory anti-regression recipe. It fails if the real reference book application, UwAmp HTTP routes, Mailpit API, or the historical mail recipe are unavailable.

## Real targets

Default local targets:

- `ASAP_REF_BOOK` root: sibling of `H:\ASAP`, normally `H:\ASAP_REF_BOOK`
- Base URL: `http://127.0.0.1/ASAP_REF_BOOK`
- Mailpit HTTP API: `http://127.0.0.1:8025`
- Historical mail endpoint: `asap-mail-recipe.php?scenario=one|two|three&transport=mailpit_smtp`

Environment overrides:

- `ASAP_RECIPE_REFBOOK_ROOT`
- `ASAP_RECIPE_REFBOOK_BASE_URL`
- `ASAP_RECIPE_MAILPIT_HTTP`

## HTTP anti-regression pages

The recipe checks the historical ASAP pages:

- `/`
- `/auto-recipe`
- `/panther-browser-testing`
- `/total-apache-recipe`
- `/asap-ui-functional-target.html`

## Mail anti-regression

The recipe calls the historical `asap-mail-recipe.php` scenarios `one`, `two`, and `three`, then verifies that Mailpit message count increases by at least three messages.

## Markers

Expected markers:

- `ASAP_REAL_REFBOOK_ROOT_OK`
- `ASAP_REAL_REFBOOK_HTTP_OK`
- `ASAP_REAL_REFBOOK_LEGACY_PAGES_OK`
- `ASAP_REAL_REFBOOK_MAIL_RECIPE_OK`
- `ASAP_REAL_FEATURE_BINDING_OK`

## Non-regression rule

A sandbox-only dashboard success is not enough. The global suite must also prove that real ASAP historical features still answer and still send real Mailpit mail.
