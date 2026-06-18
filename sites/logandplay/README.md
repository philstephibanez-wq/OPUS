# LOGANDPLAY.ORG

Projet public d'identité Log&Play.

## Rôle

Ce dépôt porte la page officielle `logandplay.org`.

La page est OPUS-powered : elle démarre le runtime OPUS local via `application/OpusRuntime.php`, puis émet une réponse publique avec `Opus\Http\PublicResponse`.

## Langues

La page expose le même nombre de langues que le RefBook OPUS :

- fr
- en
- es
- de
- uk
- it
- pl
- cs

Aucune langue non supportée ne doit être servie silencieusement.

## Statut public

Les liens visibles vers OPUS, MAESTRO et KB sont volontairement marqués `PROCHAINEMENT`.

Aucun service d'administration, Webmin, LAN, préprod, chemin serveur ou outil privé ne doit être exposé publiquement depuis cette page.

## Développement

UwAmp reste l'Apache local de développement non exposé.

Cloudflare sera reconfiguré uniquement après validation visuelle de la page.

## Runtime local

Le fichier `opus-runtime.local.json` est volontairement local et ignoré par Git.

Contrat obligatoire :

```json
{
  "opus_root": "H:\\OPUS",
  "fallback_allowed": false,
  "framework_duplication_allowed": false
}
```
