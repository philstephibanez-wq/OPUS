# OPUS FSM-FIRST ENGINE CONTRACT

STATUT: OBLIGATOIRE

SANS FSM, PAS DE MOTEUR.
SANS FSM, PAS DE PROJET OPUS VALIDE.

OPUS RESTE ASAP: AS SIMPLE AS POSSIBLE.
OPUS EST UN LEGO DE CLASSES LISIBLES, PAS UNE BOITE NOIRE.

REGLES:
- index.php est le seul point entree public web.
- index.php charge autoload/bootstrap, config minimale et FSM.
- la FSM pilote le boot, le runtime, les etats et les transitions.
- les transitions sont configurables.
- le code execute les actions autorisees par la FSM.
- chaque site/application utilise un singleton runtime.
- le routeur transforme la requete en intention, mais ne remplace jamais la FSM.
- aucun wrapper: un wrapper qui relaie vers une vraie classe ne doit pas exister.
- Kernel n est pas souverain; s il duplique Application ou court-circuite la FSM, il disparait.

FLUX CIBLE:
index.php -> autoload/bootstrap -> FSM boot -> singleton application/site -> routeur -> FSM valide transition -> controleur/action -> vue/template.

CHECKLIST AVANT PATCH RUNTIME:
- FSM presente et centrale.
- Boot pilote par FSM.
- Runtime pilote par FSM.
- Transitions configurables.
- Aucun wrapper cree/conserve/deplace.
- Aucun kernel boite noire.
