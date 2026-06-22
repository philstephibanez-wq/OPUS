<?php
declare(strict_types=1);

return [
    'fr' => [
        'home' => [
            'title' => 'Log&Play',
            'kicker' => 'plateforme officielle',
            'lead' => 'Un écosystème dédié à la création musicale, à la documentation technique et aux outils applicatifs construits autour du framework ASAP.',
            'cards' => [
                ['title' => 'ASAP Framework', 'text' => 'Explorer le socle applicatif mutualisé : routes, I18N, API, vues et packages autonomes.', 'href' => '@demo/fr/framework', 'cta' => 'Explorer ASAP'],
                ['title' => 'Maestro V5', 'text' => 'Consulter la documentation dynamique de l’environnement Maestro pour REAPER.', 'href' => '@maestro/fr', 'cta' => 'Voir Maestro'],
                ['title' => 'Architecture intégrée', 'text' => 'Comprendre l’organisation des sites, du framework et des espaces de livraison.', 'href' => 'architecture', 'cta' => 'Comprendre'],
            ],
            'sections' => [
                ['title' => 'Une base propre pour publier', 'text' => 'Log&Play regroupe les sites publics, la démonstration ASAP et la documentation Maestro dans une structure unique, transportable et maintenable.', 'items' => ['Navigation interne maîtrisée', 'Aucune dépendance externe obligatoire', 'Historique et livraisons isolés hors pages publiques']],
                ['title' => 'Pensé pour évoluer', 'text' => 'Chaque espace garde son identité, ses routes, ses contenus et ses assets, tout en partageant le même noyau applicatif.', 'items' => ['Site principal', 'ASAP Framework', 'Maestro V5']],
            ],
        ],
        'packages' => [
            'title' => 'Solutions',
            'nav' => 'Solutions',
            'kicker' => 'écosystème',
            'lead' => 'Trois espaces complémentaires réunis dans une même plateforme.',
            'sections' => [
                ['title' => 'Log&Play', 'text' => 'Le site principal présente l’écosystème, les projets et les accès publics.', 'items' => ['Accueil officiel', 'Architecture', 'Contact']],
                ['title' => 'ASAP Framework', 'text' => 'Un socle PHP léger pour organiser des sites applicatifs autonomes.', 'items' => ['Router', 'I18N', 'REST', 'FSM']],
                ['title' => 'Maestro V5', 'text' => 'La documentation dynamique du framework musical Maestro pour REAPER.', 'items' => ['Workflow', 'Contrat', 'Composants', 'FSM']],
            ],
        ],
        'architecture' => [
            'title' => 'Architecture',
            'nav' => 'Architecture',
            'kicker' => 'plateforme intégrée',
            'lead' => 'Le socle ASAP mutualise le noyau tandis que chaque site conserve ses contenus, ses routes et ses assets.',
            'sections' => [
                ['title' => 'Principe', 'text' => 'Le framework reste commun, les sites restent indépendants.', 'items' => ['Noyau applicatif mutualisé', 'Routes par espace', 'Assets par site', 'Logs, tmp et historique séparés']],
                ['title' => 'Objectif public', 'text' => 'Obtenir un site final lisible, propre et maintenable, sans libellés de développement dans l’interface.', 'items' => ['Titres définitifs', 'Navigation claire', 'Pages dynamiques', 'Liens internes']],
            ],
        ],
        'contact' => [
            'title' => 'Contact',
            'nav' => 'Contact',
            'kicker' => 'Log&Play',
            'lead' => 'Un point d’entrée clair pour les demandes liées à Log&Play, ASAP et Maestro V5.',
            'sections' => [
                ['title' => 'Disponibilité', 'text' => 'La page est prête à recevoir un formulaire ou une intégration de messagerie sans modifier la structure publique.', 'items' => ['Demande projet', 'Documentation', 'Support technique']],
            ],
        ],
    ],
    'en' => [
        'home' => [
            'title' => 'Log&Play',
            'kicker' => 'official platform',
            'lead' => 'An ecosystem for music creation, technical documentation and application tools built around the ASAP framework.',
            'cards' => [
                ['title' => 'ASAP Framework', 'text' => 'Explore the shared application core: routing, I18N, APIs, views and autonomous sites.', 'href' => '@demo/en/framework', 'cta' => 'Explore ASAP'],
                ['title' => 'Maestro V5', 'text' => 'Read the dynamic documentation for the Maestro environment for REAPER.', 'href' => '@maestro/en', 'cta' => 'Open Maestro'],
                ['title' => 'Integrated architecture', 'text' => 'Understand the organization of sites, framework and delivery spaces.', 'href' => 'architecture', 'cta' => 'Understand'],
            ],
            'sections' => [
                ['title' => 'A clean publishing base', 'text' => 'Log&Play brings the public website, ASAP showcase and Maestro documentation into one maintainable structure.', 'items' => ['Controlled internal navigation', 'No mandatory external dependency', 'History and deliveries kept outside public pages']],
                ['title' => 'Built to evolve', 'text' => 'Each space keeps its own identity, routes, content and assets while sharing the same application core.', 'items' => ['Main website', 'ASAP Framework', 'Maestro V5']],
            ],
        ],
        'packages' => ['title' => 'Solutions', 'nav' => 'Solutions', 'kicker' => 'ecosystem', 'lead' => 'Three complementary spaces gathered into one platform.'],
        'architecture' => ['title' => 'Architecture', 'nav' => 'Architecture', 'kicker' => 'integrated platform', 'lead' => 'ASAP shares the core while each site keeps its own content, routes and assets.'],
        'contact' => ['title' => 'Contact', 'nav' => 'Contact', 'kicker' => 'Log&Play', 'lead' => 'A clear entry point for Log&Play, ASAP and Maestro V5 requests.'],
    ],
    'es' => [
        'home' => [
            'title' => 'Log&Play',
            'kicker' => 'plataforma oficial',
            'lead' => 'Un ecosistema para creación musical, documentación técnica y herramientas aplicativas construidas sobre el framework ASAP.',
            'cards' => [
                ['title' => 'ASAP Framework', 'text' => 'Explorar el núcleo compartido: rutas, I18N, APIs, vistas y sitios autónomos.', 'href' => '@demo/es/arquitectura', 'cta' => 'Explorar ASAP'],
                ['title' => 'Maestro V5', 'text' => 'Consultar la documentación dinámica del entorno Maestro para REAPER.', 'href' => '@maestro/es', 'cta' => 'Ver Maestro'],
                ['title' => 'Arquitectura integrada', 'text' => 'Comprender la organización de sitios, framework y entregas.', 'href' => 'arquitectura', 'cta' => 'Comprender'],
            ],
        ],
        'packages' => ['title' => 'Soluciones', 'nav' => 'Soluciones', 'kicker' => 'ecosistema', 'lead' => 'Tres espacios complementarios reunidos en una misma plataforma.'],
        'architecture' => ['title' => 'Arquitectura', 'nav' => 'Arquitectura', 'kicker' => 'plataforma integrada', 'lead' => 'ASAP comparte el núcleo mientras cada sitio conserva sus contenidos, rutas y assets.'],
        'contact' => ['title' => 'Contacto', 'nav' => 'Contacto', 'kicker' => 'Log&Play', 'lead' => 'Un punto de entrada claro para solicitudes Log&Play, ASAP y Maestro V5.'],
    ],
];
