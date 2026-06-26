# P7A1C_BIG_TOKENIZER_EXCEPTION_PROFILER_CONTRACT_ONE_RUN

## But

Livrable global P7A1C après échecs P7A1A/P7A1B.

Ce run doit corriger le point qui a cassé P7A1B2 :

```text
CLASS_PATCH_FAILED: Opus/Core/Singleton.class.php::self
```

La détection de classes est faite par `token_get_all()` et ignore :

```text
- T_INTERFACE
- T_TRAIT
- classes anonymes `new class`
- classes abstraites pour l'implémentation automatique
```

## Règles

```text
- aucun git restore ;
- Opus/Fsm/Fsm.php ne doit jamais revenir ;
- zéro écriture avant préflight complet ;
- rollback si php -l échoue après écriture ;
- aucune interface existante non générée n'est écrasée ;
- interfaces voisines lisibles ;
- pas de Opus/Contract/PerClass ;
- pas de tools/contracts ;
- pas de Foundation/Support généré ;
- pas de faux OK si classes=0.
```

## Sorties

```text
DOC/reference/generated/json/p7a1c_contract_map.json
DOC/reference/generated/markdown/P7A1C_CONTRACT_MAP.md
```
