# OPUS — P7 LSTSAR Manager dashboard operations core

## Objectif

Créer la première surface visible du dashboard LSTSAR par site/client.

Le dashboard liste les opérations LSTSAR déclarées pour un site donné, sans encore permettre l'exécution directe.

## Contrat

Contrat principal de view-model :

```text
OPUS_LSTSAR_MANAGER_DASHBOARD_OPERATIONS_V1
```

Contrat opération :

```text
OPUS_LSTSAR_MANAGER_OPERATION_V1
```

## Données exposées

Pour chaque opération LSTSAR :

- site/client ;
- identifiant d'opération ;
- statut actif/inactif ;
- source ODBC ;
- destination ODBC ;
- mapping ;
- assignments destination ;
- couverture mapping + assignments ;
- dernier dry-run ;
- dernier run ;
- prochain run planifié ;
- liens archive/report/declaration ;
- actions disponibles.

## Politique de lancement

Ce palier n'active pas encore le lancement réel.

Actions :

- dry-run : autorisé ;
- lancement manuel : désactivé ;
- déclenchement cron/scheduler : désactivé ;
- SQL brut : interdit ;
- DDL : interdit.

Le prochain contrat de lancement doit rester séparé :

```text
P7_LSTSAR_SCHEDULER_CRON_TRIGGER_CONTRACT_CORE
```

## Assignments

Le dashboard expose la couverture destination :

- champs couverts par mapping ;
- champs couverts par assignments ;
- champs requis non couverts.

Cela permet d'identifier les opérations LSTSAR incomplètes avant lancement.
