# P112D4A — ASAP Mass Core + I18N Port

## Rôle

Porter un bloc plus massif de classes ASAP en PHP 8 strict, avec priorité I18N.

## Classes ajoutées

- `ASAP\I18N`
- `ASAP\Config`
- `ASAP\Module`
- `ASAP\Theme`
- `ASAP\Asset`

## I18N

Le portage I18N n’est pas un dictionnaire simple.

Il inclut :

- locales typées
- catalogues JSON
- messages simples
- messages pluriels
- règles `fr`, `en`, `es`
- règle russe complexe `one/few/many`
- erreurs explicites si clé/catalogue/forme absente

## Contrat

```text
NO DOC CONTRACT, NO PATCH
NO SILENT FALLBACK
I18N IS AN ENGINE, NOT A DICTIONARY
```
