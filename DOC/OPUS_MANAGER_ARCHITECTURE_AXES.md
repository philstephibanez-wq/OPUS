# OPUS Manager — axes architecture technique / espace fonctionnel

Contrat : `OPUS_MANAGER_ARCHITECTURE_AXES_CORE`

## Règle à retenir

OPUS Manager doit traiter deux axes indépendants :

```text
Axe technique
- frontend
- backend
- API
- services
- données

Axe fonctionnel
- frontoffice
- backoffice
- portail
- espace admin
- espace utilisateur
```

## Interdiction de confusion

```text
Frontend ≠ Frontoffice
Backend ≠ Backoffice
```

On ne doit jamais déduire l'espace fonctionnel depuis la couche technique, ni déduire la couche technique depuis l'espace fonctionnel.

## Exemples corrects

Un backoffice peut être client/server :

```text
frontend backoffice = UI admin
backend backoffice = API métier/admin
```

Un frontoffice peut aussi être client/server :

```text
frontend frontoffice = UI utilisateur/public
backend frontoffice = API métier/public
```

Un portail fullstack peut contenir :

```text
frontoffice public
backend interne
éventuellement backoffice d'édition
```

## Cas de référence OPUS

### LogAndPlay

```text
Espace fonctionnel : portail public / frontoffice public
Architecture technique : fullstack / portail de contenu
```

LogAndPlay n'a pas besoin par défaut d'une séparation client/server lourde. Il doit rester orienté pages, SEO, contenu, démonstrations, navigation multilingue et formulaires contrôlés.

La séparation vues / services / données reste obligatoire à l'intérieur des briques OPUS, mais elle ne nécessite pas forcément deux applications frontend/backend séparées.

### Futur KB

```text
Espace fonctionnel : frontoffice + backoffice selon les usages
Architecture technique : client/server frontend + backend
```

Le futur KB doit pouvoir utiliser :

```text
frontend frontoffice
frontend backoffice
backend API/métier/données
ACL/RBAC
SSO
contrats API
logs/audit
Ref Book
User Book
```

## Implication pour le Create Site Wizard

Le wizard doit demander séparément :

```text
1. Quel espace fonctionnel ?
   - portail public
   - frontoffice
   - backoffice
   - mixte
   - espace admin
   - espace utilisateur

2. Quelle architecture technique ?
   - fullstack / portail de contenu
   - client/server frontend + backend
   - backend/API seul
   - package Composer
```

Le choix combiné détermine ensuite :

```text
- pages à créer
- controllers nécessaires
- API ou non
- ACL/RBAC
- SSO
- ODBC/LSTSAR
- Ref Book
- User Book
- smokes
- tests Composer
```

## Règle de conception

OPUS ne crée pas seulement des pages ou des fichiers. OPUS crée une application avec une topologie explicite.

La topologie est le résultat de deux décisions indépendantes :

```text
espace fonctionnel + architecture technique
```

## Conséquence produit

L'utilisateur ne doit pas choisir directement entre des détails internes comme FSM, ACL, ODBC ou LSTSAR.

OPUS Manager doit d'abord demander :

```text
Que voulez-vous construire ?
Comment voulez-vous l'architecturer ?
```

Puis OPUS Manager déduit les modules nécessaires et les expose seulement quand ils sont utiles.

