# OPUS Manager — Create Site Technical Type First

Contrat : `OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE`

## Décision

Dans `Créer un site`, la première question doit être l'architecture technique :

```text
Fullstack
Frontend
Backend
```

L'espace fonctionnel vient ensuite seulement :

```text
portail public
frontoffice
backoffice
mixte
espace admin
espace utilisateur
```

## Règle

```text
Fullstack / Frontend / Backend = architecture technique
Frontoffice / Backoffice / Portail = espace fonctionnel
```

Ne jamais confondre les deux axes.

## Ordre du wizard

```text
CreateSiteController
├─ StepTechnicalArchitecture
│  ├─ Fullstack
│  ├─ Frontend
│  └─ Backend
├─ StepFunctionalSpace
├─ StepIdentity
├─ StepTemplate
├─ StepLanguages
├─ StepApiContract
├─ StepBackendBinding
├─ StepModules
├─ StepSecurity
├─ StepData
├─ StepOdbc
├─ StepLstsar
├─ StepComposerPlan
├─ StepComposerInstall
├─ StepSmokeTests
└─ StepSummary
```

## Branches

### Fullstack

Cas de référence : LogAndPlay.

- portail de contenu
- SEO
- pages publiques
- formulaires contrôlés
- séparation interne vues / services / données
- application complète générable par Composer

### Frontend

- backend associé obligatoire
- API obligatoire
- ACL/RBAC consommés
- SSO ou session fédérée
- aucun accès direct aux données métier

### Backend

- API
- services métier
- données
- ACL/RBAC portés ici
- SSO porté ici
- ODBC/LSTSAR possibles
- health/version/logs obligatoires

## CLI et OPUS Manager

La décision technique doit être utilisable :

```text
via CLI
via OPUS Manager
```

Les deux entrées doivent produire le même plan de création.
