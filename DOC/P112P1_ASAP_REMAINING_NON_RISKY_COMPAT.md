# P112P1 — ASAP Remaining Non-Risky Compatibility

## Objectif

Terminer le reliquat non risqué après P112O.

## Inclus

- `Validator::__construct()`
- `Validator::isPasswd()`
- `Configuration::getDatabase()`
- `Configuration::getEnv()`
- `Configuration::setEnv()`
- `Configuration::getRoutes()`
- `Configuration::get_browser()`
- `Configuration::get_os()`
- `Debug::add()`
- `Debug::addClasses()`
- `Debug::addDump()`
- `Debug::get()`
- `Debug::setDebug()`
- `LINK\Link::__toString()`
- `LINK\Link::changeClass()`
- `LINK\Link::changeId()`
- `LINK\Link::getBlock()`
- `LINK\Link::getMode()`
- `TEMPLATE\Adapter::loadTemplate()`
- `TEMPLATE\Smarty::assign()`
- `TEMPLATE\Smarty::assignAll()`
- `TEMPLATE\Smarty::parse()`
- `TEMPLATE\X64` shim
- `ASAP\Acl::canView()`
- `ASAP\Fsm::demoFlow()`
- `ASAP\View::render()`

## Exclusions

Toujours exclus de ce palier :

- Application runtime legacy complet
- Controller legacy complet
- SITE complet
- ACL complet
- FSM complet
- BDD Mysql legacy complet
- SMTP legacy complet
- VIEW\Html complet
- MENU complet

## Contrat

- Pas de fallback silencieux.
- Les runtimes non câblés comme Smarty/X64 échouent explicitement.
- Aucun redémarrage Apache.
