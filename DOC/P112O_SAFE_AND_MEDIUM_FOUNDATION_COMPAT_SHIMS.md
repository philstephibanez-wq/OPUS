# P112O — ASAP Safe + Medium Foundation Compat Shims

## Objectif

Ajouter les shims de compatibilité legacy sûrs et medium avant d'attaquer les domaines risqués.

## Inclus

SAFE / LOW :

- `Support.php`
- `SimpleXMLElementExtended.php`
- `SimpleXMLElementExtended.class.php`
- `Singleton.php`
- `Singleton.class.php`
- `Validator.php` méthodes legacy pures
- `I18N\I18n` alias legacy
- `Response::html()` / `Response::json()`
- `URL\Url` getters/setters legacy
- `ConfigLoader::getConfig()`

MEDIUM :

- `Bootstrap.php`
- `Kernel.php`
- `Package.php`
- `PackageRepository.php`

## Exclu volontairement

- Application runtime legacy complet
- Controller legacy complet
- ACL complet
- FSM complet
- BDD Mysql legacy complet
- SMTP legacy complet
- SITE complet
- VIEW/Menu legacy complet

## Contrat

- Compatibilité explicite seulement.
- Aucun fallback silencieux.
- Aucune modification Apache.
- Aucun domaine RISKY porté dans ce palier.
