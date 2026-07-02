# OPUS Manager — Language selector dedup

Contrat : `OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE`

## Décision UX

Quand le sélecteur de langue est visible, il suffit.

Le shell OPUS Manager ne doit pas afficher simultanément :

```text
Langue : Français
[select Français — FR]
```

## Correction

- Suppression du badge statique `Langue : ...` dans le shell.
- Suppression du badge statique `Langue : ...` sur Sign in.
- Conservation du sélecteur de langue.
- Conservation de la langue dans les URLs et le contexte.

## Règle

Le choix de langue doit être porté par le selecteur, pas répété en texte statique juste au-dessus.
