# P112Q2K1 — ASAP autoloader cache contract

## But

Restaurer un contrat officiel d'autoload/cache ASAP après les renommages `ASAP` / `Asap`.

## Contrat

- `ASAP\Autoload\ClassMapBuilder` construit une carte officielle `class => path`.
- `ASAP\Autoload\AutoloadCache` charge cette carte et enregistre l'autoloader.
- Le cache est généré sous `var/cache/asap/autoload/asap_classmap.php`.
- Les doublons de classes sont bloquants.
- Les classes manquantes sont bloquantes.
- Le `RecipeContext` n'utilise plus un autoload local bricolé : il passe par le cache officiel.
- La recette globale déclare `ASAP_AUTOLOADER_CACHE_OK`.

## Hors périmètre

- Pas de modification Apache.
- Pas de correction à l'aveugle de `ASAP_REF_BOOK`.
- Pas de PowerShell encodé.
- Pas de commit tant que la recette P112Q2K1 n'est pas OK.

## Commande

```cmd
php tools\autoload\asap_autoload_cache_build.php --root=H:\ASAP --assert
php tools\recipes\asap_autoload_cache_recipe.php
```
