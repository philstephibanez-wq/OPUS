# P112Q3B — TODO

## À valider chez l'utilisateur

```bat
cd /d H:\ASAP
php tools\smoke\p112q3b_secure_dispatch_gate_smoke.php
```

## Recette Panther

Avec site local lancé :

```bat
cd /d H:\ASAP
set ASAP_P112Q3B_PANTHER_URL=http://127.0.0.1/ASAP_REF_BOOK/?lang=fr
set ASAP_P112Q3B_EXPECT_TEXT=ASAP
php tools\recipes\p112q3b_secure_dispatch_gate_panther_recipe.php
```

Si Panther doit devenir bloquant :

```bat
set ASAP_P112Q3B_PANTHER_REQUIRED=1
```

## Suite proposée

`P112Q3C_ASAP_ROUTE_SECURITY_POLICY_MIGRATION`

Objectifs :

- enrichir les routes applicatives réelles avec `acl` et `fsmGuard` ;
- ajouter une recette HTTP locale complète par site ;
- brancher les rapports de recette dans le manifest global évolutif ;
- documenter les diagrammes Mermaid réels dans le RefBook.

## Surveillance

- Vérifier que les routes existantes continuent à passer avec la policy site explicite.
- Migrer progressivement les routes critiques en metadata route-aware.
- Ne pas rendre Panther obligatoire tant que le poste local n'a pas confirmé le driver/browser.
