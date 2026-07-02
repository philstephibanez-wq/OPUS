# OPUS Manager — note GitHub workspace

Contrat : `OPUS_MANAGER_ARCHITECTURE_AXES_CORE`

Cette note verrouille directement dans GitHub la règle conceptuelle OPUS Manager.

## Deux axes indépendants

Axe technique : frontend, backend, API, services, données.

Axe fonctionnel : frontoffice, backoffice, portail, espace admin, espace utilisateur.

## Règle stricte

Frontend ne signifie pas frontoffice.

Backend ne signifie pas backoffice.

Un backoffice peut être client/server avec une UI frontend et un backend API métier/admin.

Un frontoffice peut aussi être client/server avec une UI frontend et un backend API métier/public.

## Cas OPUS

LogAndPlay : fullstack / portail de contenu.

Futur KB : client/server frontend + backend.

Le Create Site Wizard doit demander séparément l'espace fonctionnel et l'architecture technique.
