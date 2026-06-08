# P112Q3E — ASAP RefBook Reflection Contract Baseline

## Objectif

Ce palier pose la base officielle du futur générateur automatique ASAP_REF_BOOK.

ASAP devient producteur de vérité documentaire structurée :

```text
Reflection = vérité technique
Attributes RefBook = vérité fonctionnelle
Tests/recettes = anti-dérive
Snapshot/API = alimentation officielle ASAP_REF_BOOK
```

## Ajouts

- `AsapRefBookClass`
- `AsapRefBookMethod`
- `RefBookInspectableInterface`
- `RefBookReflectionScanner`
- `RefBookContractValidator`
- `RefBookSnapshotBuilder`
- test unitaire/contrat fixture
- smoke runtime
- audit/snapshot RefBook réel
- recette globale ASAP anti-régression
- recette de livraison P112Q3E

## Contrat

Le scanner ne duplique pas les signatures.

Il lit par Reflection :

- classes réelles ;
- méthodes publiques réelles ;
- arguments ;
- types ;
- valeurs par défaut ;
- type de retour ;
- lignes/fichiers ;
- static/final/abstract.

Les attributes documentent ce que Reflection ne sait pas :

- rôle ;
- responsabilité ;
- comportement ;
- préconditions ;
- postconditions ;
- effets de bord ;
- erreurs ;
- tests ;
- exemples ;
- diagrammes.

## Schémas ASAP_REF_BOOK

Le snapshot contient déjà une section `schema_hints` pour préparer les générateurs de schémas :

- FSM Mermaid ;
- router graph ;
- ACL matrix ;
- security dispatch sequence.

Ces schémas devront être générés depuis les données réelles, pas dessinés manuellement quand les données existent.

## Commandes

```cmd
cd /d H:\ASAP
tests\Contract\run_refbook_reflection_contract_test.cmd
tools\smoke\run_p112q3e_refbook_reflection_contract_smoke.cmd
tools\refbook\run_p112q3e_refbook_reflection_contract_audit.cmd
tools\recipes\run_asap_global_regression_recipe.cmd
tools\recipes\run_p112q3e_delivery_recipe.cmd
```

## Notes

Le mode strict existe mais n'est pas encore la recette de livraison normale, car les classes historiques ne sont pas encore annotées avec les nouveaux attributes.

```cmd
tools\refbook\run_p112q3e_refbook_reflection_contract_strict.cmd
```
