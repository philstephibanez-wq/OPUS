# OPUS FSM-FIRST ENGINE CONTRACT

STATUT: OBLIGATOIRE

SANS FSM, PAS DE MOTEUR.
SANS FSM, PAS DE PROJET OPUS VALIDE.

OPUS reste léger, simple, explicite et professionnel.
OPUS EST UN LEGO DE CLASSES LISIBLES, PAS UNE BOITE NOIRE.

REGLES:
- index.php est le seul point entree public web.
- index.php charge autoload/bootstrap, config minimale et FSM.
- la FSM pilote le boot, le runtime, les etats et les transitions.
- les transitions sont configurables.
- le code execute les actions autorisees par la FSM.
- chaque site/application utilise un singleton runtime.
- le routeur traduit une URL localisee en signal FSM.
- le routeur ne selectionne jamais directement une page, un controleur ou un etat.
- routes.php est uniquement une projection URL localisee -> signal FSM.
- la destination fonctionnelle provient exclusivement du target_state retourne par la transition FSM.
- le source_state courant et le signal resolu sont transmis au processeur FSM.
- une transition refusee ne peut pas etre contournee par une route directe.
- le dispatcher MVC est determine depuis le target_state.
- les URL accentuees sont conservees comme projection localisee et sont encodees/normalisees par la couche HTTP.
- aucun wrapper: un wrapper qui relaie vers une vraie classe ne doit pas exister.
- Kernel n est pas souverain; s il duplique Application ou court-circuite la FSM, il disparait.

CONTRAT ROUTAGE FSM-FIRST:

```text
URL localisee
-> normalisation HTTP
-> resolution locale
-> projection URL -> signal
-> lecture source_state
-> FSM transition(source_state, signal)
-> target_state
-> ACL
-> MVC du target_state
-> vue/template
```

FORME AUTORISEE DE routes.php:

```php
return [
    'fr' => [
        '' => 'open_home',
        'sources-de-donnees' => 'open_data',
    ],
    'en' => [
        '' => 'open_home',
        'data-sources' => 'open_data',
    ],
];
```

FORME INTERDITE:

```php
return [
    'fr' => [
        'sources-de-donnees' => 'data',
    ],
];
```

Dans la forme interdite, `data` selectionne directement une destination.
La destination doit provenir du `target_state` de la FSM.

CONFIGURATION APPLICATIVE:
- la definition de l application declare un `initial_state` non vide;
- le contenu localise est indexe par identifiant d etat FSM;
- les routes localisees sont indexees par signal;
- les API conservent leur dispatcher dedie tant que leur contrat ne contourne pas la FSM.

PERSISTANCE RUNTIME:
- l etat courant est persiste par application;
- la cle runtime doit etre isolee par slug applicatif;
- la persistance ne devient jamais une seconde source de verite;
- la FSM reste seule autorite pour accepter une transition et produire le target_state.

FLUX CIBLE:
index.php -> autoload/bootstrap -> FSM boot -> singleton application/site -> routeur -> signal -> FSM valide transition -> target_state -> controleur/action -> vue/template.

CHECKLIST AVANT PATCH RUNTIME:
- FSM presente et centrale.
- Boot pilote par FSM.
- Runtime pilote par FSM.
- Transitions configurables.
- routes.php ne contient aucune destination directe.
- Toute navigation web produit un signal FSM.
- Toute destination MVC provient du target_state.
- Une transition refusee retourne une erreur et ne rend aucune page cible.
- Aucun wrapper cree/conserve/deplace.
- Aucun kernel boite noire.
