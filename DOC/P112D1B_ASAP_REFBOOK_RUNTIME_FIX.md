# P112D1B — ASAP Reference Book Runtime Fix

## Rôle

Corriger la lecture des paramètres par défaut du routeur ASAP.

## Cause racine

`Router::fromXml()` parcourait `defaults->param` sans vérifier explicitement l’existence du nœud `defaults`.

## Correction

Le routeur ne lit les paramètres par défaut que si le nœud `defaults` est explicitement présent.

## Contrat

- Aucun fallback de route.
- Aucune route implicite.
- Erreur explicite si un paramètre par défaut est mal formé.
