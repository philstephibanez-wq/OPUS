# P7A1D4 — BIG WEB PROFILER EXCEPTION PIPELINE CONFIGURED FSM

## Objectif

Installer un premier Web Profiler OPUS lisible, rendu par `.score`, avec menu par collector, timeline de traces, pipeline PHP error -> OPUS exception, et transitions FSM runtime externalisées dans `config/fsm_runtime/`.

## Contraintes

- Aucune restauration de `Opus/Fsm/Fsm.php`.
- Aucune transition runtime hardcodée dans une classe PHP.
- Les transitions obligatoires OPUS sont des fichiers de configuration.
- Les collectors produisent des données, jamais du HTML.
- Le rendu HTML passe par OPUS Score templates.
- Rollback automatique en cas d'échec de lint.
