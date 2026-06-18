# OPUS_REF_BOOK

Official Reference Book for Opus 8.1.0 "Lysenko".

Composer identity:

- logandplay/opus 8.1.0
- logandplay/opus-8.1.0-lysenko-reference-book 8.1.0

## Scope

OPUS_REF_BOOK is a Reference Book, not a User Book.

It documents short, stable and verifiable facts:

- package identity and version contract;
- installation references;
- public API and class catalog;
- framework domains;
- routing, MVC and ScoreTemplate contracts;
- I18N rules;
- Mermaid diagrams when they clarify a contract;
- legal and license summary.

Long tutorials, recipes and many examples belong to a separate OPUS_USER_BOOK.

## Runtime contract

Opus Application -> SiteResolver -> Router -> SecureDispatchGate -> ControllerDispatcher -> Controller -> Service -> ViewModel -> ScoreTemplateRenderer -> HTML Response.

## MVC contract

OPUS_REF_BOOK is a strict MVC application.

- Model: data repositories, manifests, I18N, release metadata, catalog metadata and validated application state.
- Controller: request routing, service orchestration and ViewModel construction only.
- View: ScoreTemplate `.score` templates only.

All page, partial and component representations belong in `.score` files. PHP must not concatenate page HTML.

## Template contract

ScoreTemplate is the only rendering system for the Reference Book.

Valid templates include:

- `application/reference/templates/layout.score`
- `application/reference/templates/pages/*.score`
- future `application/reference/templates/partials/*.score`
- future `application/reference/templates/components/*.score`

Twig templates are obsolete and must not be used in OPUS_REF_BOOK.

## Rendering contract

The active renderer is the OPUS ScoreTemplate renderer. The previous Twig layer is removed from the active architecture and must not be restored.

## Local development

Use Composer to install OPUS dependencies. In local workspace mode the package can resolve `logandplay/opus` from `H:/OPUS` through the Composer path repository declared in `composer.json`.

Production packages must use normal Composer/package distribution, not workstation-specific paths.

## License

OPUS and OPUS_REF_BOOK follow the proprietary OPUS license previously defined for the OPUS framework family.