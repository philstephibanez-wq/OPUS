# P3_OPUS_ROOT_CLEANUP_AUDIT

## Objectif

Auditer la propreté de la racine du framework OPUS avant tout déplacement ou suppression.

Ce palier répond à deux constats :

- `Opus/` contient encore trop de fichiers directement à sa racine.
- des wrappers modernes (`Acl.php`, `Fsm.php`, `I18n.php`, `Router.php`) ont été ajoutés trop vite et doivent être validés ou supprimés.

## Règle de prudence

P3 est strictement read-only.

Aucun fichier framework, site, vendor, cache, log ou configuration n'est modifié par ce palier.

## Pourquoi `Acl.php` à la racine est suspect

Dans l'esprit ASAP/OPUS, les responsabilités doivent être rangées par domaine métier :

```text
Opus/Acl/
Opus/Fsm/
Opus/I18n/
Opus/Controller/
Opus/Router/
```

Un fichier racine comme `Opus/Acl.php` n'est acceptable que s'il est une façade officielle clairement documentée. Sinon il doit être déplacé, fusionné avec la classe historique ou supprimé.

## Classification attendue

L'audit classe les fichiers directs sous `Opus/` en :

- `KEEP_CORE` : cœur OPUS historique ou contrat central.
- `KEEP_FACADE_REVIEW` : façade utile mais à confirmer.
- `MODERN_LAYER_REVIEW` : couche ajoutée durant OPUS reborn, à comparer avec ASAP historique.
- `ROOT_WRAPPER_REVIEW` : wrapper moderne à déplacer, fusionner ou supprimer.
- `REVIEW_UNKNOWN` : fichier non classé, à examiner avant patch.

## Scripts outils

Le script principal est :

```text
tools/audit_opus_root_cleanup_p3.py
```

Il liste aussi les scripts présents directement sous `tools/` afin de préparer une future phase d'archivage ou de suppression des scripts obsolètes.

## Commandes

```cmd
cd /d H:\OPUS
python tools\audit_opus_root_cleanup_p3.py
git status --short
```

## Prochaine étape possible

Après lecture du rapport :

```text
P3B_OPUS_ROOT_WRAPPER_DECISION
```

Objectif : traiter uniquement les wrappers évidents (`Acl.php`, `Fsm.php`, `I18n.php`, `Router.php`) sans toucher aux sites ni aux couches `Kernel/Package`.
