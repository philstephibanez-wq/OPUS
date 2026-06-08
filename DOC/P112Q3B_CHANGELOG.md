# P112Q3B — CHANGELOG

## Added

- `ASAP\Security\SecureDispatchGate`.
- `ASAP\Security\SecureDispatchDecision`.
- Metadata `acl` / `fsmGuard` portées par `RouteMatch`.
- Hydratation route-aware dans `Router::fromXml()`.
- Smoke runtime/statique `p112q3b_secure_dispatch_gate_smoke.php`.
- Recette robotisée Panther-aware.

## Changed

- `Application::run()` matche la route candidate avant le gate sécurité.
- Le dispatch controller/action est appelé uniquement après succès du gate.

## Security

- Renforce le contrat `default deny` existant : route inconnue, transition inconnue, ACL inconnue ou metadata mal formée cassent clairement.
- L'ACL de route supporte `resource:privilege` et `role:resource:privilege`.

## Validation sandbox

```text
php -l framework/Asap/Application/Application.php
php -l framework/Asap/Routing/RouteMatch.php
php -l framework/Asap/Routing/Router.php
php -l framework/Asap/Security/SecureDispatchDecision.php
php -l framework/Asap/Security/SecureDispatchGate.php
php -l tools/smoke/p112q3b_secure_dispatch_gate_smoke.php
php -l tools/recipes/p112q3b_secure_dispatch_gate_panther_recipe.php
```
