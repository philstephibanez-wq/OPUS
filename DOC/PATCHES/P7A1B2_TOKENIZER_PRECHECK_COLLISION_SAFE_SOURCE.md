# P7A1B2 TOKENIZER PRECHECK COLLISION SAFE

Run local OPUS sans reset Git.

Objectif : corriger P7A1B en empêchant toute écriture avant préflight complet.

Garanties :

- détection des classes par `token_get_all()` ;
- `T_CLASS` uniquement ;
- classes anonymes exclues ;
- interfaces et traits exclus ;
- classes abstraites exclues ;
- `Opus/Fsm/Fsm.php` doit rester absent ;
- collisions détectées avant génération ;
- interfaces existantes trackées jamais écrasées ;
- artefacts non trackés de P7A1B nettoyés s'ils portent un marqueur généré ;
- rollback si le lint PHP post-écriture échoue.
