# ASAP Router Attribute Compiler

## Pipeline

```text
Autoloader / Composer classmap
  -> ClassIndex
  -> AttributeRouteProvider
  -> RouteManifestCompiler
  -> var/cache/asap/routes.manifest.php
  -> Router runtime
```

## Pourquoi ne pas compiler dans l'autoload ?

Compiler dans l'autoload créerait des effets de bord :

```text
class load -> scan -> reflection -> write cache
```

ASAP interdit ce comportement. La compilation est une action explicite.

## Compilation explicite

Une commande dédiée sera ajoutée dans un palier suivant :

```cmd
php tools/asap route:compile
```

Pour P112Q1, le smoke vérifie déjà le scanner, le compiler, les conflits et le
manifest PHP.
