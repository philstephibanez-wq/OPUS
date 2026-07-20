# OWASYS

Application OPUS structurée selon le modèle MVC piloté par FSM.

- `application/default/` : socle MVC et assets communs.
- `application/<module>/` : MVC et assets spécifiques greffés sur `default`.
- `config/fsm.yaml` : table de transitions OPUS.
- `config/acl.yaml` : configuration ACL.
- `config/sso.yaml` : configuration SSO.
- `var/cache/` et `var/logs/` : données runtime.
- `www/index.php` : point d'entrée public unique donnant la main à OPUS.
