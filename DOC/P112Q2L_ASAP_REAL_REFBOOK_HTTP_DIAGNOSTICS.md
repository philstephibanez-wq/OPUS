# P112Q2L — ASAP real RefBook HTTP diagnostics

## But

Empêcher les erreurs HTTP réelles `ASAP_REF_BOOK` de tomber comme un simple `500` opaque dans la recette globale.

## Contrat

- `RealFeatureBindingRecipe` écrit un diagnostic JSON pour chaque page réelle testée.
- Le corps HTTP brut est archivé à côté du diagnostic JSON.
- Le message d'échec inclut :
  - label de page,
  - URL,
  - statut HTTP,
  - chemin du diagnostic JSON,
  - body excerpt.
- La recette réussie expose `ASAP_REAL_FEATURE_BINDING_DIAGNOSTICS_OK`.
- Les rapports restent dans `var/recipes/.../real_feature_binding/diagnostics`.

## Objectif anti-régression

Une panne comme `ASAP_REAL_REFBOOK_HTTP_PAGE_FAILED: home :: ... :: 500` ne doit plus jamais obliger à deviner le fichier PHP fautif sans artefact.

## Marqueurs

- `ASAP_REAL_FEATURE_BINDING_DIAGNOSTICS_OK`
- `ASAP_REAL_REFBOOK_HTTP_DIAGNOSTIC`
- `ASAP_REAL_REFBOOK_MAIL_DIAGNOSTIC`

## Hors périmètre

- Pas de modification Apache.
- Pas de modification directe de `H:\ASAP_REF_BOOK`.
- Pas de PowerShell encodé.
- Pas de fallback silencieux.

## Anti-régression exacte

Un opaque 500 RefBook doit toujours produire un diagnostic JSON, un body excerpt et un body brut archivé.
