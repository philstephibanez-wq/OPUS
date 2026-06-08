# P112Q3E2A — ASAP RefBook ACL live symbol fix

## Scope

Correctif ciblé du palier P112Q3E2 après validation sur la cible réelle `H:\ASAP`.

L'audit ACL réel a détecté trois violations restantes :

- `ASAP\Acl\AccessDeniedException` sans `AsapRefBookClass` ;
- `ASAP\Acl\Acl` sans `AsapRefBookClass` ;
- `ASAP\Acl\Acl::canView` sans `AsapRefBookMethod`.

## Changements

- Ajoute `AsapRefBookClass` sur `ASAP\Acl\AccessDeniedException`.
- Ajoute `AsapRefBookClass` sur `ASAP\Acl\Acl`.
- Ajoute `RefBookInspectableInterface` sur `ASAP\Acl\Acl`.
- Ajoute `AsapRefBookMethod` sur `ASAP\Acl\Acl::refBookDomain`.
- Ajoute `AsapRefBookMethod` sur `ASAP\Acl\Acl::canView`.
- Met à jour `RefBookAclMetadataContractTest` pour refléter la surface ACL réelle : 11 symboles et 30 méthodes publiques.

## Contrat

Le domaine ACL doit être validé par :

```text
P112Q3E2_REFBOOK_ACL_METADATA_CONTRACT_UNIT_OK
P112Q3E2_REFBOOK_ACL_METADATA_SMOKE_OK
P112Q3E2_REFBOOK_ACL_METADATA_STRICT_OK
ASAP_GLOBAL_REGRESSION_RECIPE_OK
P112Q3E2_DELIVERY_RECIPE_OK
ExitCode=0
```

## Fichiers modifiés

```text
framework/Asap/Acl/AccessDeniedException.php
framework/Asap/Acl/Acl.php
tests/Contract/RefBookAclMetadataContractTest.php
```
