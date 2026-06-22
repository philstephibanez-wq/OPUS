<?php
declare(strict_types=1);

return [
    'fr' => [
        'home' => [
            'title' => 'ASAP Framework',
            'kicker' => 'framework applicatif',
            'lead' => 'Une vitrine technique pour présenter le framework mutualisé, les packages autonomes, le routeur, l’I18N, les APIs et les URLs accentuées.',
            'cards' => [
                [
                    'title' => 'Framework',
                    'text' => 'Kernel, routeur, vues, I18N, FSM et ACL mutualisés.',
                    'href' => 'framework',
                ],
                [
                    'title' => 'Router',
                    'text' => 'Routes propres en mode intégré et domaine dédié.',
                    'href' => 'router',
                ],
                [
                    'title' => 'Sites',
                    'text' => 'Chaque site garde son www, ses logs, son tmp et son history.',
                    'href' => 'packages',
                ],
                [
                    'title' => 'URL accentuée',
                    'text' => 'Test interne avec caractères accentués.',
                    'href' => 'démo-interne',
                ],
                [
                    'title' => 'REST',
                    'text' => 'Endpoints JSON internes de test.',
                    'href' => '@api/demo/site',
                ],
                [
                    'title' => 'Articles',
                    'text' => 'Données de démonstration type blog.',
                    'href' => 'articles',
                ],
                [
                    'title' => 'Galerie',
                    'text' => 'Exemple de galerie intégrée sans lien externe.',
                    'href' => 'galerie',
                ],
                [
                    'title' => 'Contact',
                    'text' => 'Formulaire statique de démonstration.',
                    'href' => 'contact',
                ],
            ],
        ],
        'framework' => [
            'title' => 'Framework mutualisé',
            'kicker' => 'ASAP core',
            'lead' => 'Le framework reste commun, les sites restent des packages.',
            'sections' => [
                [
                    'title' => 'Principe',
                    'text' => 'Le noyau ASAP calcule les chemins dynamiquement et charge le package demandé.',
                    'items' => [
                        'Aucun chemin UwAmp codé en dur',
                        'Un seul front controller',
                        'Assets servis depuis le www du package',
                        'Erreurs explicites si un fichier contrat manque',
                    ],
                ],
            ],
        ],
        'router' => [
            'title' => 'Router',
            'kicker' => 'dispatch',
            'lead' => 'Le routeur supporte le mode mode intégré et le mode domaine dédié sans changer le code.',
            'sections' => [
                [
                    'title' => 'Exemples',
                    'text' => 'Les mêmes packages fonctionnent avec /LOGANDPLAY_ASAP_LOCAL_PACKAGES/demo/fr/router ou demo.logandplay.localhost/fr/router.',
                    'items' => [
                        'Décodage UTF-8 des routes',
                        '404 explicite avec path et route',
                        'API séparée sous /api',
                        'Pas de redirection vers localhost externe',
                    ],
                ],
            ],
        ],
        'packages' => [
            'title' => 'Sites autonomes',
            'kicker' => 'architecture modulaire',
            'lead' => 'Chaque site est un package isolé fonctionnellement, tout en utilisant le framework commun.',
            'sections' => [
                [
                    'title' => 'Arborescence',
                    'text' => 'Un package contient routes, local, controllers, views, templates, helpers, www, logs, tmp et history.',
                    'items' => [
                        'www = assets publics',
                        'logs = logs du package',
                        'tmp = fichiers temporaires du package',
                        'history = patchs et notes hors site public',
                    ],
                ],
            ],
        ],
        'controllers' => [
            'title' => 'Controllers',
            'kicker' => 'MVC',
            'lead' => 'Les contrôleurs restent dans le package et n’ont pas besoin de connaître le chemin racine UwAmp.',
            'sections' => [
                [
                    'title' => 'Contrat',
                    'text' => 'Le contrôleur reçoit une intention de route et prépare les données de vue.',
                    'items' => [
                        'Pas de chemin absolu figé',
                        'Pas de logique de site dans le framework',
                        'API et pages séparées',
                    ],
                ],
            ],
        ],
        'views' => [
            'title' => 'Views',
            'kicker' => 'rendu',
            'lead' => 'Les vues sont alimentées par le package et rendues via le layout commun.',
            'sections' => [
                [
                    'title' => 'But',
                    'text' => 'Garder les templates propres et éviter la pollution des dossiers publics.',
                    'items' => [
                        'Contenu structuré',
                        'HTML échappé',
                        'Navigation générée en interne',
                    ],
                ],
            ],
        ],
        'templates' => [
            'title' => 'Templates',
            'kicker' => 'layout',
            'lead' => 'Les templates servent à mutualiser la présentation sans casser l’autonomie des packages.',
            'sections' => [
                [
                    'title' => 'Approche',
                    'text' => 'Le framework fournit le squelette, le package fournit son contenu et ses assets.',
                    'items' => [
                        'CSS du site',
                        'JS du site',
                        'Aucun CDN obligatoire',
                    ],
                ],
            ],
        ],
        'models' => [
            'title' => 'Models / DB',
            'kicker' => 'données',
            'lead' => 'Démonstration de couche modèle : les données peuvent venir de fichiers, SQL ou services internes.',
            'sections' => [
                [
                    'title' => 'Démo',
                    'text' => 'Cette version utilise des tableaux PHP pour rester installable immédiatement.',
                    'items' => [
                        'Prêt pour DB',
                        'Pas de dépendance externe',
                        'Chemins dynamiques',
                    ],
                ],
            ],
        ],
        'fsm' => [
            'title' => 'FSM',
            'kicker' => 'workflow',
            'lead' => 'Trace simple du workflow routeur pour montrer le squelette data-driven.',
        ],
        'acl' => [
            'title' => 'ACL',
            'kicker' => 'sécurité',
            'lead' => 'L’ACL est volontairement minimale ici : seules les pages publiques sont servies.',
            'sections' => [
                [
                    'title' => 'Extension',
                    'text' => 'La classe ACL peut ensuite vérifier rôles, sessions ou droits package par package.',
                    'items' => [
                        '403 explicite',
                        'Visibilité déclarative',
                        'Pas de contournement silencieux',
                    ],
                ],
            ],
        ],
        'rest' => [
            'title' => 'REST',
            'kicker' => 'api',
            'lead' => 'Endpoints JSON internes inclus pour valider le package et les chemins.',
            'sections' => [
                [
                    'title' => 'Endpoints',
                    'text' => 'Deux routes API sont fournies.',
                    'items' => [
                        'GET /demo/api/ping',
                        'GET /demo/api/site',
                        'Réponse JSON UTF-8',
                    ],
                ],
            ],
        ],
        'i18n' => [
            'title' => 'I18N',
            'kicker' => 'langues',
            'lead' => 'Le switch FR / EN / ES fonctionne sans chemin d’installation hardcodé.',
            'sections' => [
                [
                    'title' => 'Contrat',
                    'text' => 'Chaque package expose ses langues dans local/<lang>.php.',
                    'items' => [
                        'Fallback explicite [*key*]',
                        'Fichier manquant = erreur claire',
                        'Switch conservant la page courante quand possible',
                    ],
                ],
            ],
        ],
        'accented' => [
            'title' => 'Démo interne accentuée',
            'kicker' => '/fr/démo-interne',
            'lead' => 'Cette page valide les caractères accentués dans une URL interne.',
            'sections' => [
                [
                    'title' => 'Validation',
                    'text' => 'La route est décodée en UTF-8 et dispatchée sans lien externe.',
                    'items' => [
                        'é, è, à, ç dans le chemin',
                        'Fonctionne en mode dossier local',
                        'Fonctionne aussi en vhost si configuré',
                    ],
                ],
            ],
        ],
        'debug_logs' => [
            'title' => 'Debug / logs',
            'kicker' => 'observabilité',
            'lead' => 'Les logs doivent rester séparés par package.',
            'sections' => [
                [
                    'title' => 'Dossiers',
                    'text' => 'Le package demo possède son propre dossier logs et tmp.',
                    'items' => [
                        'sites/demo/logs',
                        'sites/demo/tmp',
                        'history séparé des pages publiques',
                    ],
                ],
            ],
        ],
        'mailpit' => [
            'title' => 'Mailpit',
            'kicker' => 'email dev',
            'lead' => 'Page de démonstration pour réserver l’intégration Mailpit locale.',
            'sections' => [
                [
                    'title' => 'But',
                    'text' => 'Tester l’envoi mail dans l’environnement sans polluer l’extérieur.',
                    'items' => [
                        'Aucun envoi réel dans cette livraison',
                        'Point d’entrée de future intégration',
                        'Lien externe non requis',
                    ],
                ],
            ],
        ],
        'articles' => [
            'title' => 'Articles',
            'kicker' => 'contenu',
            'lead' => 'Exemple de section éditoriale pour tester routes, vues et données.',
            'sections' => [
                [
                    'title' => 'Articles démo',
                    'text' => 'Trois blocs simulés pour tester les templates.',
                    'items' => [
                        'Architecture packages',
                        'I18N et URLs',
                        'Historique propre',
                    ],
                ],
            ],
        ],
        'gallery' => [
            'title' => 'Galerie',
            'kicker' => 'médias',
            'lead' => 'Galerie intégrée de démonstration sans dépendance externe.',
            'sections' => [
                [
                    'title' => 'Médias',
                    'text' => 'Les assets publics doivent vivre dans www/assets.',
                    'items' => [
                        'Images futures dans sites/demo/www/assets/img',
                        'Pas de CDN obligatoire',
                        'Package transportable',
                    ],
                ],
            ],
        ],
        'contact' => [
            'title' => 'Contact',
            'kicker' => 'formulaire',
            'lead' => 'Formulaire statique de démonstration prêt à brancher sur un contrôleur.',
            'sections' => [
                [
                    'title' => 'À brancher',
                    'text' => 'Le traitement POST n’est pas activé pour éviter les effets de bord.',
                    'items' => [
                        'Validation côté contrôleur',
                        'Logs package',
                        'Mailpit possible',
                    ],
                ],
            ],
        ],
    ],
    'en' => [
        'home' => [
            'title' => 'Full ASAP demo',
            'kicker' => 'demo package',
            'lead' => 'A rich internal demo for the shared framework, autonomous packages, routing, I18N, APIs and accented URL checks.',
            'cards' => [
                [
                    'title' => 'Framework',
                    'text' => 'Kernel, routeur, vues, I18N, FSM et ACL mutualisés.',
                    'href' => 'framework',
                ],
                [
                    'title' => 'Router',
                    'text' => 'Routes propres en mode intégré et domaine dédié.',
                    'href' => 'router',
                ],
                [
                    'title' => 'Sites',
                    'text' => 'Chaque site garde son www, ses logs, son tmp et son history.',
                    'href' => 'packages',
                ],
                [
                    'title' => 'URL accentuée',
                    'text' => 'Test interne avec caractères accentués.',
                    'href' => 'démo-interne',
                ],
                [
                    'title' => 'REST',
                    'text' => 'Endpoints JSON internes de test.',
                    'href' => '@api/demo/site',
                ],
                [
                    'title' => 'Articles',
                    'text' => 'Données de démonstration type blog.',
                    'href' => 'articles',
                ],
                [
                    'title' => 'Galerie',
                    'text' => 'Exemple de galerie intégrée sans lien externe.',
                    'href' => 'galerie',
                ],
                [
                    'title' => 'Contact',
                    'text' => 'Formulaire statique de démonstration.',
                    'href' => 'contact',
                ],
            ],
        ],
        'framework' => [
            'title' => 'Shared framework',
            'kicker' => 'ASAP core',
            'lead' => 'The framework stays shared while each website remains an autonomous package.',
            'sections' => [
                [
                    'title' => 'Principe',
                    'text' => 'Le noyau ASAP calcule les chemins dynamiquement et charge le package demandé.',
                    'items' => [
                        'Aucun chemin UwAmp codé en dur',
                        'Un seul front controller',
                        'Assets servis depuis le www du package',
                        'Erreurs explicites si un fichier contrat manque',
                    ],
                ],
            ],
        ],
        'router' => [
            'title' => 'Router',
            'kicker' => 'dispatch',
            'lead' => 'The router supports folder mode and vhost mode without code changes.',
            'sections' => [
                [
                    'title' => 'Exemples',
                    'text' => 'Les mêmes packages fonctionnent avec /LOGANDPLAY_ASAP_LOCAL_PACKAGES/demo/fr/router ou demo.logandplay.localhost/fr/router.',
                    'items' => [
                        'Décodage UTF-8 des routes',
                        '404 explicite avec path et route',
                        'API séparée sous /api',
                        'Pas de redirection vers localhost externe',
                    ],
                ],
            ],
        ],
        'packages' => [
            'title' => 'Autonomous packages',
            'kicker' => 'architecture modulaire',
            'lead' => 'Each website is isolated as a package while using the shared framework.',
            'sections' => [
                [
                    'title' => 'Arborescence',
                    'text' => 'Un package contient routes, local, controllers, views, templates, helpers, www, logs, tmp et history.',
                    'items' => [
                        'www = assets publics',
                        'logs = logs du package',
                        'tmp = fichiers temporaires du package',
                        'history = patchs et notes hors site public',
                    ],
                ],
            ],
        ],
        'controllers' => [
            'title' => 'Controllers',
            'kicker' => 'MVC',
            'lead' => 'Controllers stay inside the package and do not know the UwAmp root path.',
            'sections' => [
                [
                    'title' => 'Contrat',
                    'text' => 'Le contrôleur reçoit une intention de route et prépare les données de vue.',
                    'items' => [
                        'Pas de chemin absolu figé',
                        'Pas de logique de site dans le framework',
                        'API et pages séparées',
                    ],
                ],
            ],
        ],
        'views' => [
            'title' => 'Views',
            'kicker' => 'rendering',
            'lead' => 'Views are fed by package data and rendered through the common layout.',
            'sections' => [
                [
                    'title' => 'But',
                    'text' => 'Garder les templates propres et éviter la pollution des dossiers publics.',
                    'items' => [
                        'Contenu structuré',
                        'HTML échappé',
                        'Navigation générée en interne',
                    ],
                ],
            ],
        ],
        'templates' => [
            'title' => 'Templates',
            'kicker' => 'layout',
            'lead' => 'Templates share presentation without breaking package autonomy.',
            'sections' => [
                [
                    'title' => 'Approche',
                    'text' => 'Le framework fournit le squelette, le package fournit son contenu et ses assets.',
                    'items' => [
                        'CSS du site',
                        'JS du site',
                        'Aucun CDN obligatoire',
                    ],
                ],
            ],
        ],
        'models' => [
            'title' => 'Models / DB',
            'kicker' => 'data',
            'lead' => 'Model-layer demo with file, SQL or internal-service ready data.',
            'sections' => [
                [
                    'title' => 'Démo',
                    'text' => 'Cette version utilise des tableaux PHP pour rester installable immédiatement.',
                    'items' => [
                        'Prêt pour DB',
                        'Pas de dépendance externe',
                        'Chemins dynamiques',
                    ],
                ],
            ],
        ],
        'fsm' => [
            'title' => 'FSM',
            'kicker' => 'workflow',
            'lead' => 'Simple routing workflow trace to demonstrate the data-driven skeleton.',
        ],
        'acl' => [
            'title' => 'ACL',
            'kicker' => 'security',
            'lead' => 'The ACL is intentionally minimal here: only public pages are served.',
            'sections' => [
                [
                    'title' => 'Extension',
                    'text' => 'La classe ACL peut ensuite vérifier rôles, sessions ou droits package par package.',
                    'items' => [
                        '403 explicite',
                        'Visibilité déclarative',
                        'Pas de contournement silencieux',
                    ],
                ],
            ],
        ],
        'rest' => [
            'title' => 'REST',
            'kicker' => 'api',
            'lead' => 'Internal JSON endpoints included to validate package paths.',
            'sections' => [
                [
                    'title' => 'Endpoints',
                    'text' => 'Deux routes API sont fournies.',
                    'items' => [
                        'GET /demo/api/ping',
                        'GET /demo/api/site',
                        'Réponse JSON UTF-8',
                    ],
                ],
            ],
        ],
        'i18n' => [
            'title' => 'I18N',
            'kicker' => 'languages',
            'lead' => 'The FR / EN / ES switch works without hardcoded installation roots.',
            'sections' => [
                [
                    'title' => 'Contrat',
                    'text' => 'Chaque package expose ses langues dans local/<lang>.php.',
                    'items' => [
                        'Fallback explicite [*key*]',
                        'Fichier manquant = erreur claire',
                        'Switch conservant la page courante quand possible',
                    ],
                ],
            ],
        ],
        'accented' => [
            'title' => 'Internal demo',
            'kicker' => '/en/internal-demo',
            'lead' => 'This page mirrors the accented French URL test with an internal route.',
            'sections' => [
                [
                    'title' => 'Validation',
                    'text' => 'La route est décodée en UTF-8 et dispatchée sans lien externe.',
                    'items' => [
                        'é, è, à, ç dans le chemin',
                        'Fonctionne en mode dossier local',
                        'Fonctionne aussi en vhost si configuré',
                    ],
                ],
            ],
        ],
        'debug_logs' => [
            'title' => 'Debug / logs',
            'kicker' => 'observability',
            'lead' => 'Logs remain separated per package.',
            'sections' => [
                [
                    'title' => 'Dossiers',
                    'text' => 'Le package demo possède son propre dossier logs et tmp.',
                    'items' => [
                        'sites/demo/logs',
                        'sites/demo/tmp',
                        'history séparé des pages publiques',
                    ],
                ],
            ],
        ],
        'mailpit' => [
            'title' => 'Mailpit',
            'kicker' => 'dev email',
            'lead' => 'Demo page reserving the Mailpit integration point.',
            'sections' => [
                [
                    'title' => 'But',
                    'text' => 'Tester l’envoi mail dans l’environnement sans polluer l’extérieur.',
                    'items' => [
                        'Aucun envoi réel dans cette livraison',
                        'Point d’entrée de future intégration',
                        'Lien externe non requis',
                    ],
                ],
            ],
        ],
        'articles' => [
            'title' => 'Articles',
            'kicker' => 'content',
            'lead' => 'Editorial demo section for routes, views and data.',
            'sections' => [
                [
                    'title' => 'Articles démo',
                    'text' => 'Trois blocs simulés pour tester les templates.',
                    'items' => [
                        'Architecture packages',
                        'I18N et URLs',
                        'Historique propre',
                    ],
                ],
            ],
        ],
        'gallery' => [
            'title' => 'Gallery',
            'kicker' => 'media',
            'lead' => 'Local demo gallery without external dependencies.',
            'sections' => [
                [
                    'title' => 'Médias',
                    'text' => 'Les assets publics doivent vivre dans www/assets.',
                    'items' => [
                        'Images futures dans sites/demo/www/assets/img',
                        'Pas de CDN obligatoire',
                        'Package transportable',
                    ],
                ],
            ],
        ],
        'contact' => [
            'title' => 'Contact',
            'kicker' => 'form',
            'lead' => 'Static demo form ready to be wired to a controller.',
            'sections' => [
                [
                    'title' => 'À brancher',
                    'text' => 'Le traitement POST n’est pas activé pour éviter les effets de bord.',
                    'items' => [
                        'Validation côté contrôleur',
                        'Logs package',
                        'Mailpit possible',
                    ],
                ],
            ],
        ],
    ],
    'es' => [
        'home' => [
            'title' => 'Demo ASAP completa',
            'kicker' => 'paquete demo',
            'lead' => 'Una demo interna rica para probar framework compartido, paquetes autónomos, router, I18N, APIs y URLs internas.',
            'cards' => [
                [
                    'title' => 'Framework',
                    'text' => 'Kernel, routeur, vues, I18N, FSM et ACL mutualisés.',
                    'href' => 'framework',
                ],
                [
                    'title' => 'Router',
                    'text' => 'Routes propres en mode intégré et domaine dédié.',
                    'href' => 'router',
                ],
                [
                    'title' => 'Sites',
                    'text' => 'Chaque site garde son www, ses logs, son tmp et son history.',
                    'href' => 'packages',
                ],
                [
                    'title' => 'URL accentuée',
                    'text' => 'Test interne avec caractères accentués.',
                    'href' => 'démo-interne',
                ],
                [
                    'title' => 'REST',
                    'text' => 'Endpoints JSON internes de test.',
                    'href' => '@api/demo/site',
                ],
                [
                    'title' => 'Articles',
                    'text' => 'Données de démonstration type blog.',
                    'href' => 'articles',
                ],
                [
                    'title' => 'Galerie',
                    'text' => 'Exemple de galerie intégrée sans lien externe.',
                    'href' => 'galerie',
                ],
                [
                    'title' => 'Contact',
                    'text' => 'Formulaire statique de démonstration.',
                    'href' => 'contact',
                ],
            ],
        ],
        'framework' => [
            'title' => 'Framework compartido',
            'kicker' => 'ASAP core',
            'lead' => 'El framework queda compartido y cada sitio sigue siendo un paquete autónomo.',
            'sections' => [
                [
                    'title' => 'Principe',
                    'text' => 'Le noyau ASAP calcule les chemins dynamiquement et charge le package demandé.',
                    'items' => [
                        'Aucun chemin UwAmp codé en dur',
                        'Un seul front controller',
                        'Assets servis depuis le www du package',
                        'Erreurs explicites si un fichier contrat manque',
                    ],
                ],
            ],
        ],
        'router' => [
            'title' => 'Router',
            'kicker' => 'dispatch',
            'lead' => 'El router soporta modo carpeta y modo vhost sin cambiar código.',
            'sections' => [
                [
                    'title' => 'Exemples',
                    'text' => 'Les mêmes packages fonctionnent avec /LOGANDPLAY_ASAP_LOCAL_PACKAGES/demo/fr/router ou demo.logandplay.localhost/fr/router.',
                    'items' => [
                        'Décodage UTF-8 des routes',
                        '404 explicite avec path et route',
                        'API séparée sous /api',
                        'Pas de redirection vers localhost externe',
                    ],
                ],
            ],
        ],
        'packages' => [
            'title' => 'Paquetes autónomos',
            'kicker' => 'architecture modulaire',
            'lead' => 'Cada sitio se aísla como paquete usando el framework común.',
            'sections' => [
                [
                    'title' => 'Arborescence',
                    'text' => 'Un package contient routes, local, controllers, views, templates, helpers, www, logs, tmp et history.',
                    'items' => [
                        'www = assets publics',
                        'logs = logs du package',
                        'tmp = fichiers temporaires du package',
                        'history = patchs et notes hors site public',
                    ],
                ],
            ],
        ],
        'controllers' => [
            'title' => 'Controladores',
            'kicker' => 'MVC',
            'lead' => 'Los controladores quedan dentro del paquete.',
            'sections' => [
                [
                    'title' => 'Contrat',
                    'text' => 'Le contrôleur reçoit une intention de route et prépare les données de vue.',
                    'items' => [
                        'Pas de chemin absolu figé',
                        'Pas de logique de site dans le framework',
                        'API et pages séparées',
                    ],
                ],
            ],
        ],
        'views' => [
            'title' => 'Vistas',
            'kicker' => 'render',
            'lead' => 'Las vistas se alimentan con datos del paquete.',
            'sections' => [
                [
                    'title' => 'But',
                    'text' => 'Garder les templates propres et éviter la pollution des dossiers publics.',
                    'items' => [
                        'Contenu structuré',
                        'HTML échappé',
                        'Navigation générée en interne',
                    ],
                ],
            ],
        ],
        'templates' => [
            'title' => 'Plantillas',
            'kicker' => 'layout',
            'lead' => 'Las plantillas comparten presentación.',
            'sections' => [
                [
                    'title' => 'Approche',
                    'text' => 'Le framework fournit le squelette, le package fournit son contenu et ses assets.',
                    'items' => [
                        'CSS du site',
                        'JS du site',
                        'Aucun CDN obligatoire',
                    ],
                ],
            ],
        ],
        'models' => [
            'title' => 'Modelos / DB',
            'kicker' => 'datos',
            'lead' => 'Demo de capa modelo lista para DB.',
            'sections' => [
                [
                    'title' => 'Démo',
                    'text' => 'Cette version utilise des tableaux PHP pour rester installable immédiatement.',
                    'items' => [
                        'Prêt pour DB',
                        'Pas de dépendance externe',
                        'Chemins dynamiques',
                    ],
                ],
            ],
        ],
        'fsm' => [
            'title' => 'FSM',
            'kicker' => 'workflow',
            'lead' => 'Traza simple del workflow de routing.',
        ],
        'acl' => [
            'title' => 'ACL',
            'kicker' => 'seguridad',
            'lead' => 'ACL mínima: solo páginas públicas.',
            'sections' => [
                [
                    'title' => 'Extension',
                    'text' => 'La classe ACL peut ensuite vérifier rôles, sessions ou droits package par package.',
                    'items' => [
                        '403 explicite',
                        'Visibilité déclarative',
                        'Pas de contournement silencieux',
                    ],
                ],
            ],
        ],
        'rest' => [
            'title' => 'REST',
            'kicker' => 'api',
            'lead' => 'Endpoints JSON internos.',
            'sections' => [
                [
                    'title' => 'Endpoints',
                    'text' => 'Deux routes API sont fournies.',
                    'items' => [
                        'GET /demo/api/ping',
                        'GET /demo/api/site',
                        'Réponse JSON UTF-8',
                    ],
                ],
            ],
        ],
        'i18n' => [
            'title' => 'I18N',
            'kicker' => 'idiomas',
            'lead' => 'Switch FR / EN / ES sin rutas hardcodeadas.',
            'sections' => [
                [
                    'title' => 'Contrat',
                    'text' => 'Chaque package expose ses langues dans local/<lang>.php.',
                    'items' => [
                        'Fallback explicite [*key*]',
                        'Fichier manquant = erreur claire',
                        'Switch conservant la page courante quand possible',
                    ],
                ],
            ],
        ],
        'accented' => [
            'title' => 'Demo interna',
            'kicker' => '/es/demo-interna',
            'lead' => 'Página interna equivalente al test francés acentuado.',
            'sections' => [
                [
                    'title' => 'Validation',
                    'text' => 'La route est décodée en UTF-8 et dispatchée sans lien externe.',
                    'items' => [
                        'é, è, à, ç dans le chemin',
                        'Fonctionne en mode dossier local',
                        'Fonctionne aussi en vhost si configuré',
                    ],
                ],
            ],
        ],
        'debug_logs' => [
            'title' => 'Debug / logs',
            'kicker' => 'observabilidad',
            'lead' => 'Logs separados por paquete.',
            'sections' => [
                [
                    'title' => 'Dossiers',
                    'text' => 'Le package demo possède son propre dossier logs et tmp.',
                    'items' => [
                        'sites/demo/logs',
                        'sites/demo/tmp',
                        'history séparé des pages publiques',
                    ],
                ],
            ],
        ],
        'mailpit' => [
            'title' => 'Mailpit',
            'kicker' => 'email dev',
            'lead' => 'Punto reservado para integración Mailpit local.',
            'sections' => [
                [
                    'title' => 'But',
                    'text' => 'Tester l’envoi mail dans l’environnement sans polluer l’extérieur.',
                    'items' => [
                        'Aucun envoi réel dans cette livraison',
                        'Point d’entrée de future intégration',
                        'Lien externe non requis',
                    ],
                ],
            ],
        ],
        'articles' => [
            'title' => 'Artículos',
            'kicker' => 'contenido',
            'lead' => 'Sección editorial de demo.',
            'sections' => [
                [
                    'title' => 'Articles démo',
                    'text' => 'Trois blocs simulés pour tester les templates.',
                    'items' => [
                        'Architecture packages',
                        'I18N et URLs',
                        'Historique propre',
                    ],
                ],
            ],
        ],
        'gallery' => [
            'title' => 'Galería',
            'kicker' => 'medios',
            'lead' => 'Galería local sin dependencias externas.',
            'sections' => [
                [
                    'title' => 'Médias',
                    'text' => 'Les assets publics doivent vivre dans www/assets.',
                    'items' => [
                        'Images futures dans sites/demo/www/assets/img',
                        'Pas de CDN obligatoire',
                        'Package transportable',
                    ],
                ],
            ],
        ],
        'contact' => [
            'title' => 'Contacto',
            'kicker' => 'formulario',
            'lead' => 'Formulario estático de demostración.',
            'sections' => [
                [
                    'title' => 'À brancher',
                    'text' => 'Le traitement POST n’est pas activé pour éviter les effets de bord.',
                    'items' => [
                        'Validation côté contrôleur',
                        'Logs package',
                        'Mailpit possible',
                    ],
                ],
            ],
        ],
    ],
];
