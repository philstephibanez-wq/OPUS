<?php
declare(strict_types=1);

$root = getcwd();

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_ARCHITECTURE_AXES_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$docDir = $root . DIRECTORY_SEPARATOR . 'DOC';
if (!is_dir($docDir) && !mkdir($docDir, 0775, true) && !is_dir($docDir)) {
    fwrite(STDERR, 'OPUS_MANAGER_ARCHITECTURE_AXES_DOC_DIR_FAILED' . PHP_EOL);
    exit(1);
}

$doc = <<<'MD'
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

MD;

$docFile = $docDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_ARCHITECTURE_AXES.md';
if (file_put_contents($docFile, $doc . PHP_EOL) === false) {
    fwrite(STDERR, 'OPUS_MANAGER_ARCHITECTURE_AXES_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$scopeFile = $docDir . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$scope = is_file($scopeFile) ? file_get_contents($scopeFile) : '# OPUS P7 — portée de clôture finale' . PHP_EOL;
if (!is_string($scope)) {
    fwrite(STDERR, 'OPUS_MANAGER_ARCHITECTURE_AXES_SCOPE_READ_FAILED' . PHP_EOL);
    exit(1);
}

if (!str_contains($scope, 'OPUS_MANAGER_ARCHITECTURE_AXES_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_ARCHITECTURE_AXES_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- OPUS Manager doit traiter deux axes indépendants : axe technique et axe fonctionnel.' . PHP_EOL;
    $scope .= '- Axe technique : frontend, backend, API, services, données.' . PHP_EOL;
    $scope .= '- Axe fonctionnel : frontoffice, backoffice, portail, espace admin, espace utilisateur.' . PHP_EOL;
    $scope .= '- Frontend ne signifie pas frontoffice ; backend ne signifie pas backoffice.' . PHP_EOL;
    $scope .= '- Un backoffice peut être client/server ; un frontoffice peut aussi être client/server.' . PHP_EOL;
    $scope .= '- LogAndPlay reste le cas de référence fullstack / portail de contenu.' . PHP_EOL;
    $scope .= '- Le futur KB reste le cas de référence client/server frontend + backend.' . PHP_EOL;
    $scope .= '- Le Create Site Wizard doit demander séparément espace fonctionnel et architecture technique.' . PHP_EOL;
}

if (file_put_contents($scopeFile, $scope) === false) {
    fwrite(STDERR, 'OPUS_MANAGER_ARCHITECTURE_AXES_SCOPE_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'OPUS_MANAGER_ARCHITECTURE_AXES_CORE_OK' . PHP_EOL;
