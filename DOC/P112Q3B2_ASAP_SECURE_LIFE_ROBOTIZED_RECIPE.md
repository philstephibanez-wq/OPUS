# P112Q3B2 — ASAP Secure Life Robotized Recipe

## Rôle

Ajouter une vraie recette robotisée visible pour le chantier `secure by design`.

Cette recette ne se contente pas d'un smoke technique. Elle produit un rapport HTML visible, un rapport JSON, un rapport Markdown et tente l'envoi du rapport par mail.

## Scénario couvert

La recette exécute une matrice réelle via les classes ASAP :

```text
Router -> RouteMatch -> SecureDispatchGate -> StateMachine -> AccessControl
```

Elle couvre trois profils visibles :

- `guest` en `FR` : accès public autorisé, administration refusée ;
- `editor` en `ES` : édition autorisée, administration refusée ;
- `admin` en `EN` : administration autorisée.

## Rapports produits

```text
var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.html
var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.md
var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.json
```

Le lanceur `.cmd` ouvre un serveur PHP local visible sur `127.0.0.1` et ouvre le rapport HTML dans le navigateur.

## Mail

Le mode par défaut du lanceur principal est `phpmail` avec mail requis :

```text
ASAP_P112Q3B2_MAIL_MODE=phpmail
ASAP_P112Q3B2_MAIL_REQUIRED=1
```

Si `mail()` n'est pas configuré côté PHP local, la recette échoue explicitement avec `MAIL_NOT_DELIVERED` au lieu de produire un faux succès.

Un lanceur SMTP local est fourni pour Mailpit/MailHog :

```text
tools/recipes/run_p112q3b2_secure_life_robotized_recipe_mailpit.cmd
```

Il utilise par défaut :

```text
ASAP_P112Q3B2_SMTP_HOST=127.0.0.1
ASAP_P112Q3B2_SMTP_PORT=1025
```

## Panther

Panther est optionnel sauf si :

```text
ASAP_P112Q3B2_PANTHER_REQUIRED=1
```

Si Panther est absent, le rapport indique `PANTHER_CLIENT_NOT_AVAILABLE` ou `PANTHER_AUTOLOAD_NOT_FOUND` sans prétendre que le test navigateur Panther a réussi.

## VS Code

Trois tâches sont ajoutées :

- `ASAP · Smoke P112Q3B2 Secure Life Robotized`
- `ASAP · Recipe P112Q3B2 Secure Life Robotized`
- `ASAP · Recipe P112Q3B2 Secure Life Mailpit`

## Contrat

- Aucun `.bat`.
- Aucun redémarrage Apache/UwAmp.
- Aucun envoi mail simulé en succès.
- Aucun faux succès Panther.
- Aucun changement BDD.
- Aucun patch `ASAP_REF_BOOK`.
