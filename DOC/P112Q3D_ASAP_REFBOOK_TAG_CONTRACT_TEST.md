# P112Q3D — ASAP RefBook Tag Contract Test

## Rôle

Ajouter un contrôle exécutable garantissant que les sources publiques ASAP restent exploitables par le générateur du Reference Book.

## Contrat

- Chaque classe/interface/trait/enum publique du framework doit porter un bloc `ASAP_REFBOOK` local.
- Chaque méthode publique doit porter son propre bloc `ASAP_REFBOOK` local.
- Une balise de classe ne couvre jamais silencieusement toutes les méthodes.
- Le contrôle strict échoue explicitement tant que les méthodes publiques manquantes ne sont pas complétées.
- Aucun patch source automatique n'est appliqué par ce palier.

## Commandes

Smoke :

```cmd
cd /d H:\ASAP
tools\smoke\run_p112q3d_refbook_tag_contract_smoke.cmd
```

Audit non bloquant :

```cmd
cd /d H:\ASAP
tools\quality\run_p112q3d_refbook_tag_contract_audit.cmd
```

Test strict :

```cmd
cd /d H:\ASAP
tests\Contract\run_refbook_tag_contract_test.cmd
```

## Rapports

Le contrôle génère :

```text
var\reports\p112q3d_refbook_tag_contract\latest.json
var\reports\p112q3d_refbook_tag_contract\latest.md
var\reports\p112q3d_refbook_tag_contract\latest.html
```

## Note importante

Le test strict est volontairement exigeant. Si les méthodes publiques n'ont pas encore leurs balises `ASAP_REFBOOK`, il doit échouer avec `P112Q3D_REFBOOK_TAG_CONTRACT_STRICT_FAILED`. Ce n'est pas un bug : c'est le verrou qualité demandé pour la génération du Reference Book.
