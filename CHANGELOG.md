# CHANGELOG — OPUS

## P116B_SCORETEMPLATE_NATIVE_FINAL_CONTRACT

- Renforce `Opus\Template\ScoreTemplateRenderer` avec le contrat ScoreTemplate v1 : interpolation échappée, interpolation brute explicite, includes contrôlés, conditions simples, boucles simples, métadonnées `loop.*` et filtres whitelistés.
- Ajoute le smoke ciblé `tools/smoke_p116b_score_template_final.php`.
- Met à jour la recette `TemplateRecipe` pour valider ScoreTemplate comme cible native, sans adapter legacy.
- Retire le contrat `Opus\Template\Adapter` de la cible officielle.
- Documente le contrat dans `DOC/P116B_SCORETEMPLATE_NATIVE_CONTRACT.md`.
- Clarifie dans le README que ScoreTemplate est la cible framework, avec Twig seulement temporaire pour migration applicative.
