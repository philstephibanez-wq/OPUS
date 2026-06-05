# P112D4C — ASAP Legacy Huge Recovery Port

## Rôle

Rattraper le portage ASAP en respectant prioritairement les domaines historiques
du framework original.

## Domaines legacy portés / réconciliés

- `ASAP\CONTROLLER`
- `ASAP\VIEW`
- `ASAP\TEMPLATE`
- `ASAP\I18N\I18n`
- `ASAP\HELPER`
- `ASAP\MENU`
- `ASAP\URL`
- `ASAP\LINK`
- `ASAP\MODEL`
- `ASAP\REST`
- `ASAP\BDD`
- `ASAP\MAIL`
- `ASAP\FTP`
- `ASAP\SMTP`
- `ASAP\Configuration`
- `ASAP\ConfigLoader`
- `ASAP\Validator`
- `ASAP\Debug`
- `ASAP\Exception`

## Décision

Le portage fidèle est prioritaire.

Les classes modernes ajoutées précédemment ne sont pas supprimées brutalement,
mais le pipeline ASAP repointe sur le domaine historique `ASAP\CONTROLLER`.

## Contrat

- Portage fidèle d’abord.
- Évolution ensuite, séparée et validée.
- Pas de fallback silencieux.
- Dépendances externes non configurées = erreurs explicites.
