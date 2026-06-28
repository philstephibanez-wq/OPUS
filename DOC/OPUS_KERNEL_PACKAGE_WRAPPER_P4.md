# P4 — OPUS Kernel / Package / wrappers audit

## But

Ce palier ne modifie pas le runtime. Il audite les fichiers modernes introduits dans OPUS reborn avant de decider ce qui doit rester dans le framework.

La priorite reste :

- OPUS doit rester **As Simple As Possible**.
- Les classes historiques doivent rester reconnaissables jusqu'a leur revue contractuelle.
- Le singleton par site/application et les getters/setters automatiques sont une piece centrale du contrat OPUS.
- Aucun fichier ne doit etre supprime ou deplace sans comprendre ses dependances.

## Perimetre audite

- `Opus/Kernel.php`
- `Opus/Package.php`
- `Opus/PackageRepository.php`
- `Opus/Request.php`
- `Opus/Response.php`
- `Opus/Support.php`
- `Opus/View.php`
- `Opus/Acl.php`
- `Opus/Fsm.php`
- `Opus/I18n.php`
- `Opus/Router.php`

## Intention

Les fichiers `Acl.php`, `Fsm.php`, `I18n.php`, `Router.php` a la racine `Opus/` sont des wrappers modernes. Ils ne doivent pas etre valides comme structure finale tant que `Kernel.php` n'a pas ete compare avec le coeur historique `OPUS_Application`.

`PackageRepository.php` est aussi suspect car il depend de `sites/` et du package `logandplay`. Un framework mutualise ne doit pas imposer un site precis.

## Commande

```cmd
cd /d H:\OPUS
python tools\audits\audit_opus_kernel_package_wrapper_p4.py
git status --short
```

## Attendu

```text
P4_OPUS_KERNEL_PACKAGE_WRAPPER_AUDIT_OK
git status clean
```

## Suite logique

- P4B : inspecter les classes historiques `Application`, `Router`, `I18n`, `Acl`, `Fsm`.
- P4C : decider du sort de `Kernel`.
- P4D : supprimer/deplacer les wrappers racine seulement apres decision.
