# P113D3B — ASAP RefBook documentation assets

## Cible

`H:\ASAP`

## Rôle

Créer les assets documentaires officiels référencés par les annotations ASAP_REFBOOK du framework ASAP, afin que l'application `ASAP_REF_BOOK` puisse afficher les exemples et diagrammes sans produire de diagnostic d'assets manquants.

## Contrat

- ASAP reste la source de vérité du framework.
- RefBook ne génère pas de contenu de remplacement.
- Les assets sont stockés côté framework mutualisé :
  - `DOC/refbook/examples/*.php`
  - `DOC/refbook/diagrams/*.mmd`
- Aucun framework n'est copié dans `ASAP_REF_BOOK`.
- Aucun fallback silencieux.

## Assets ajoutés

### Exemples

- `acl-overview`
- `acl-condition`
- `acl-error`
- `acl-refbook-domain`
- `fsm-definition`
- `fsm-basic-transition`
- `fsm-action`
- `fsm-error`
- `fsm-refbook-domain`
- `response-overview`
- `response-html`
- `response-send`
- `http-refbook-domain`
- `attribute-routing`
- `secure-dispatch-gate`
- `routing-refbook-domain`

### Diagrammes

- `acl-runtime`
- `fsm-runtime`
- `http-runtime`
- `routing-runtime`
- `secure-dispatch-runtime`
