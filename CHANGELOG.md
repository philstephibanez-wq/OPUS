# CHANGELOG â€” P112C5

## AjoutÃ©
- Squelette HTML navigable du Reference Book ASAP.
- Pages HTML : accueil, architecture, FSM, ACL.
- CSS/JS dÃ©diÃ©s Ã  la documentation gÃ©nÃ©rÃ©e.
- Navigation JSON sÃ©parÃ©e.

## P112Q2I0_ASAP_GITHUB_BOOTSTRAP
- Prepared ASAP for private GitHub publication.
- Added bootstrap documentation and automation wrappers.
- Added marked `.gitignore` block for secrets, runtime data and future LSTSA run outputs.

## P112Q2I1_ASAP_SITE_MULTI_DB_AND_LSTSA_CONTRACT
- Added site multi-database configuration collection and loader.
- Added first public LSTSA namespace and XML contract loader.
- Added field constraints with type, length and byte validation.
- Added append-only JSON/Markdown report archive writer.
- Added automation smoke recipe.

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_BASELINE -->
## P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_BASELINE

- Ajout du runner CLI LSTSA baseline hors requÃªte HTTP.
- Ajout du scheduler baseline pour crÃ©er une demande de run LSTSA.
- Ajout queue/locks/heartbeats fichier sous `var/lstsa/` hors Git.
- Ajout recette smoke test avec rapport JSON/MD append-only.
<!-- END MAESTRO_WORKSPACE P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_BASELINE -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I3_ASAP_LSTSA_BATCH_CHECKPOINT_EXECUTOR -->
## P112Q2I3_ASAP_LSTSA_BATCH_CHECKPOINT_EXECUTOR

- Ajout du premier exÃ©cuteur LSTSA batch/checkpoint.
- Validation stricte input puis output aprÃ¨s transformation.
- Quarantine runtime pour lignes rejetÃ©es.
- Archives runtime append-only pour lignes stockÃ©es.
- Rapports JSON/MD enrichis avec artifacts.
<!-- END MAESTRO_WORKSPACE P112Q2I3_ASAP_LSTSA_BATCH_CHECKPOINT_EXECUTOR -->

