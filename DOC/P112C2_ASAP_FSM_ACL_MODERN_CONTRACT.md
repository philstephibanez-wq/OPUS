# P112C2 — ASAP FSM + ACL — Contrat moderne

Statut : contrat de portage avant code
Portée : ASAP framework
Langage cible : PHP 8+
Documentation : phpDocumentor + Markdown + Mermaid
Sortie Reference Book : HTML dans DOC/reference/generated/html/

## 1. Décision

ASAP conserve et modernise deux moteurs nobles issus de l’ASAP original : FSM et ACL.

FSM = moteur d’état générique.
ACL = moteur d’autorisation générique.

## 2. Pipeline cible

REQUEST -> SiteResolver -> SiteState FSM Guard -> ACL Guard -> Router -> Dispatcher -> Controller -> Service -> ViewModel -> Renderer

## 3. Contrat FSM

La FSM possède uniquement les états, signaux, transitions, actions d’état et mémoire d’état.

Responsabilités autorisées : gérer état courant, signal, transition, état suivant, action déclarée, mémoire, stack, fifo, lifo, timeout, export sûr, diagramme Mermaid.

Interdictions FSM : pas de droits, pas de route, pas de rendu, pas de template, pas de fallback silencieux, pas de sauvegarde en destructeur, pas de unserialize non cadré, pas de dépendance GraphViz.

Objets FSM cibles : StateMachine, StateDefinition, SignalDefinition, TransitionDefinition, TransitionResult, StateMemory, StateStack, StateQueue, StateActionInterface, StateMachineException.

Erreurs FSM : FSM_SIGNAL_UNKNOWN, FSM_STATE_UNKNOWN, FSM_TRANSITION_NOT_ALLOWED, FSM_ACTION_NOT_FOUND, FSM_MEMORY_CONTRACT_FAILED, FSM_TIMEOUT_REACHED, FSM_SERIALIZATION_FORBIDDEN, FSM_CONTRACT_FAILED.

## 4. Contrat ACL

L’ACL décide qui peut faire quoi sur quelle ressource, dans un contexte validé.

Responsabilités autorisées : rôles, ressources, privilèges, allow, deny, conditions déclarées, décision d’accès typée.

Interdictions ACL : pas d’état global site, pas de route, pas de rendu, pas de mutation requête, pas d’autorisation silencieuse, pas de singleton global, pas de Reflection arbitraire non cadrée.

Objets ACL cibles : AccessControl, RoleDefinition, ResourceDefinition, PrivilegeDefinition, AccessRule, AccessDecision, AccessContext, AccessConditionInterface, AccessDeniedException, AccessControlException.

Erreurs ACL : ACL_ROLE_UNKNOWN, ACL_RESOURCE_UNKNOWN, ACL_PRIVILEGE_UNKNOWN, ACL_CONDITION_FAILED, ACL_ACCESS_DENIED, ACL_CONTEXT_INVALID, ACL_CONTRACT_FAILED.

## 5. Mermaid remplace GraphViz

GraphViz devient une référence historique uniquement.

Mermaid est la cible officielle moderne pour les diagrammes FSM et ACL.

## 6. Documentation obligatoire

NO DOC CONTRACT, NO PATCH.

Chaque classe publique, méthode publique, interface publique, exception publique et DTO public doit documenter : visibilité, rôle, responsabilité, paramètres, retour, erreurs, effets de bord, contrat, invariants, palier d’introduction.

## 7. Reference Book HTML

La sortie documentaire officielle est HTML : DOC/reference/generated/html/.

Les sources documentaires restent PHPDoc, Markdown et Mermaid.

Le HTML généré ne doit jamais être mélangé au code source.

## 8. Prochaine étape

P112C3 doit créer uniquement le squelette PHP documenté des objets FSM + ACL.

Aucun portage massif PHP5. Aucune copie brute. Aucun fallback silencieux.
