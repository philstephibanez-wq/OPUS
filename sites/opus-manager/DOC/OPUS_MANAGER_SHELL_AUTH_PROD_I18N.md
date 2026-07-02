# OPUS Manager — Auth / Sign in / I18N finalize

Contrat : `OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE`

## Objectif

Finaliser la brique OPUS Manager restée dirty :

- router auth-aware
- Sign in
- Logout
- session dev contrôlée
- i18n officiel UE + ukrainien `uk`
- déduplication langue
- prod sans profiler/debug activable par URL

## Règles

- `admin / admin` uniquement pour le mode dev local.
- Le sélecteur de langue suffit.
- Aucun badge `Langue : ...` si le selecteur existe.
- `/opus-manager/login` et `/opus-manager/signin` redirigent vers `/opus-manager/sign-in`.
- Les routes OPUS Manager protégées redirigent vers Sign in si non connecté.
- Les controllers restent séparés : SignInController, LogoutController, CreateSiteController, etc.

## Smokes

- Sign in rendu en `uk`.
- Sélecteur visible.
- Aucune répétition `Langue :`.
- Auth dev OK.
- Create Site rendu après auth.
- Router contient aliases login/signin.
- `uk` est obligatoire.
