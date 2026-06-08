# P112Q3B4 — ASAP Recipe Email-Safe Report + Form Scenarios

## Rôle

Corriger la recette robotisée évolutive P112Q3B2/P112Q3B3 pour produire un rapport e-mail dédié plus compatible, tout en ajoutant des scénarios de formulaires réels.

## Contrat

```text
ASAP framework only
secure by design
no silent fallback
no fake Panther OK
no fake mail OK
.cmd only, no .bat
browser page != e-mail template
```

## Changements

- Ajout d'un rendu e-mail séparé : `p112q3b2_build_email_safe_html_report()`.
- Conservation du rendu navigateur moderne existant.
- Ajout d'un rapport HTML e-mail disque : `var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe_email.html`.
- Envoi du mail avec le template e-mail-safe, pas avec la page navigateur.
- Ajout de formulaires visibles sur la page de test.
- Ajout de scénarios POST réels dans la matrice robotisée : guest, editor, admin.
- Ajout d'un scénario négatif `GET` sur route formulaire `POST`.
- Renforcement de `Router::match()` : méthode HTTP explicite, erreur `ASAP_ROUTE_METHOD_NOT_ALLOWED` si la route existe mais la méthode est interdite.

## Résultat attendu

La page navigateur reste riche et visible, avec les 3 utilisateurs et les formulaires.

Le mail Mailpit doit afficher un HTML plus simple et plus robuste. L'onglet `HTML Check` peut encore donner des avertissements selon le client simulé, mais le template ne doit plus dépendre de CSS de page web moderne.

## Commandes

```cmd
cd /d H:\ASAP
tools\smoke\run_p112q3b4_email_safe_forms_smoke.cmd
tools\recipes\run_p112q3b2_secure_life_robotized_recipe_mailpit.cmd
```

## Panther

Panther reste explicite : si `Symfony\Component\Panther\Client` n'est pas disponible dans l'autoload chargé, le rapport doit rester `Panther: SKIPPED`. Aucun faux succès n'est autorisé.
