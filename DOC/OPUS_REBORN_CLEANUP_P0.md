# OPUS Reborn Cleanup P0

Objectif: nettoyer l'identite OPUS sans refonte.

Ce palier ne change pas la logique historique. Il aligne seulement les noms et les chemins du framework OPUS.

Scope autorise: Opus, www, composer.json, README.md.

Scope interdit: sites, Sites, vendor, var/cache, var/log, var/tmp.

Regles:

- Une classe issue de l'ancien framework doit rester reconnaissable.
- Un patch de renommage ne change pas la logique.
- La convention ASAP_* devient OPUS_* dans le framework.
- Les namespaces modernes ASAP deviennent Opus.
- Les chemins framework/ASAP deviennent le chemin reel Opus.
- Controler devient Controller.
- Scafold devient Scaffold.
- Componants reste Componants pour ce palier.

Commandes locales:

cd /d H:\OPUS
git pull --ff-only
python tools\opus_reborn_cleanup_p0.py
python tools\opus_reborn_cleanup_p0.py --write
python tools\smoke_opus_reborn_cleanup_p0.py
git status --short

Resultats attendus:

P0_OPUS_REBORN_CLEANUP_OK
P0_OPUS_REBORN_CLEANUP_SMOKE_OK

Doctrine: OPUS doit rester l'heritier direct de l'esprit As Simple As Possible: plus propre, plus coherent, mais jamais plus opaque.
