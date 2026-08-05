<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\File\Json;

/** Canonical scaffold for an autonomous OPUS site/application. */
final class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanInterface
{
    public const PROFILE_FRONTEND = 'frontend';
    public const PROFILE_BACKEND = 'backend';
    public const PROFILE_FULLSTACK = 'fullstack';

    private const FALLBACK_LOCALE = 'fr';

    /** @var list<string> */
    private const SUPPORTED_LOCALES = [
        'bg',
        'hr',
        'cs',
        'da',
        'nl',
        'en',
        'et',
        'fi',
        'fr',
        'de',
        'el',
        'hu',
        'ga',
        'it',
        'lv',
        'lt',
        'mt',
        'pl',
        'pt',
        'ro',
        'sk',
        'sl',
        'es',
        'sv',
        'uk',
    ];

    /** @var array<string,array<string,string>> */
    private const DEFAULT_MESSAGES = [
        'bg' => [
            'language' => 'Език',
            'menu.home' => 'Начало',
            'menu.architecture' => 'Архитектура',
            'menu.router' => 'Маршрутизатор',
            'menu.modules' => 'Модули',
            'menu.controllers' => 'Контролери',
            'menu.views' => 'Изгледи',
            'menu.models' => 'Модели',
            'menu.i18n' => 'Интернационализация',
            'error.title' => 'Грешка на OPUS',
            'error.request_failed' => 'Заявката е неуспешна.',
        ],
        'hr' => [
            'language' => 'Jezik',
            'menu.home' => 'Početna',
            'menu.architecture' => 'Arhitektura',
            'menu.router' => 'Usmjerivač',
            'menu.modules' => 'Moduli',
            'menu.controllers' => 'Kontroleri',
            'menu.views' => 'Prikazi',
            'menu.models' => 'Modeli',
            'menu.i18n' => 'Internacionalizacija',
            'error.title' => 'Pogreška OPUS-a',
            'error.request_failed' => 'Zahtjev nije uspio.',
        ],
        'cs' => [
            'language' => 'Jazyk',
            'menu.home' => 'Domů',
            'menu.architecture' => 'Architektura',
            'menu.router' => 'Směrovač',
            'menu.modules' => 'Moduly',
            'menu.controllers' => 'Kontrolery',
            'menu.views' => 'Pohledy',
            'menu.models' => 'Modely',
            'menu.i18n' => 'Internacionalizace',
            'error.title' => 'Chyba OPUS',
            'error.request_failed' => 'Požadavek selhal.',
        ],
        'da' => [
            'language' => 'Sprog',
            'menu.home' => 'Hjem',
            'menu.architecture' => 'Arkitektur',
            'menu.router' => 'Router',
            'menu.modules' => 'Moduler',
            'menu.controllers' => 'Controllere',
            'menu.views' => 'Visninger',
            'menu.models' => 'Modeller',
            'menu.i18n' => 'Internationalisering',
            'error.title' => 'OPUS-fejl',
            'error.request_failed' => 'Anmodningen mislykkedes.',
        ],
        'nl' => [
            'language' => 'Taal',
            'menu.home' => 'Start',
            'menu.architecture' => 'Architectuur',
            'menu.router' => 'Router',
            'menu.modules' => 'Modules',
            'menu.controllers' => 'Controllers',
            'menu.views' => 'Weergaven',
            'menu.models' => 'Modellen',
            'menu.i18n' => 'Internationalisatie',
            'error.title' => 'OPUS-fout',
            'error.request_failed' => 'De aanvraag is mislukt.',
        ],
        'en' => [
            'language' => 'Language',
            'menu.home' => 'Home',
            'menu.architecture' => 'Architecture',
            'menu.router' => 'Router',
            'menu.modules' => 'Modules',
            'menu.controllers' => 'Controllers',
            'menu.views' => 'Views',
            'menu.models' => 'Models',
            'menu.i18n' => 'Internationalization',
            'error.title' => 'OPUS error',
            'error.request_failed' => 'The request failed.',
        ],
        'et' => [
            'language' => 'Keel',
            'menu.home' => 'Avaleht',
            'menu.architecture' => 'Arhitektuur',
            'menu.router' => 'Marsruuter',
            'menu.modules' => 'Moodulid',
            'menu.controllers' => 'Kontrollerid',
            'menu.views' => 'Vaated',
            'menu.models' => 'Mudelid',
            'menu.i18n' => 'Rahvusvahelistamine',
            'error.title' => 'OPUS-e viga',
            'error.request_failed' => 'Päring nurjus.',
        ],
        'fi' => [
            'language' => 'Kieli',
            'menu.home' => 'Etusivu',
            'menu.architecture' => 'Arkkitehtuuri',
            'menu.router' => 'Reititin',
            'menu.modules' => 'Moduulit',
            'menu.controllers' => 'Ohjaimet',
            'menu.views' => 'Näkymät',
            'menu.models' => 'Mallit',
            'menu.i18n' => 'Kansainvälistäminen',
            'error.title' => 'OPUS-virhe',
            'error.request_failed' => 'Pyyntö epäonnistui.',
        ],
        'fr' => [
            'language' => 'Langue',
            'menu.home' => 'Accueil',
            'menu.architecture' => 'Architecture',
            'menu.router' => 'Routeur',
            'menu.modules' => 'Modules',
            'menu.controllers' => 'Contrôleurs',
            'menu.views' => 'Vues',
            'menu.models' => 'Modèles',
            'menu.i18n' => 'Internationalisation',
            'error.title' => 'Erreur OPUS',
            'error.request_failed' => 'La requête a échoué.',
        ],
        'de' => [
            'language' => 'Sprache',
            'menu.home' => 'Startseite',
            'menu.architecture' => 'Architektur',
            'menu.router' => 'Router',
            'menu.modules' => 'Module',
            'menu.controllers' => 'Controller',
            'menu.views' => 'Ansichten',
            'menu.models' => 'Modelle',
            'menu.i18n' => 'Internationalisierung',
            'error.title' => 'OPUS-Fehler',
            'error.request_failed' => 'Die Anfrage ist fehlgeschlagen.',
        ],
        'el' => [
            'language' => 'Γλώσσα',
            'menu.home' => 'Αρχική',
            'menu.architecture' => 'Αρχιτεκτονική',
            'menu.router' => 'Δρομολογητής',
            'menu.modules' => 'Αρθρώματα',
            'menu.controllers' => 'Ελεγκτές',
            'menu.views' => 'Προβολές',
            'menu.models' => 'Μοντέλα',
            'menu.i18n' => 'Διεθνοποίηση',
            'error.title' => 'Σφάλμα OPUS',
            'error.request_failed' => 'Το αίτημα απέτυχε.',
        ],
        'hu' => [
            'language' => 'Nyelv',
            'menu.home' => 'Kezdőlap',
            'menu.architecture' => 'Architektúra',
            'menu.router' => 'Útválasztó',
            'menu.modules' => 'Modulok',
            'menu.controllers' => 'Vezérlők',
            'menu.views' => 'Nézetek',
            'menu.models' => 'Modellek',
            'menu.i18n' => 'Nemzetköziesítés',
            'error.title' => 'OPUS-hiba',
            'error.request_failed' => 'A kérés sikertelen.',
        ],
        'ga' => [
            'language' => 'Teanga',
            'menu.home' => 'Baile',
            'menu.architecture' => 'Ailtireacht',
            'menu.router' => 'Ródaire',
            'menu.modules' => 'Modúil',
            'menu.controllers' => 'Rialaitheoirí',
            'menu.views' => 'Amhairc',
            'menu.models' => 'Samhlacha',
            'menu.i18n' => 'Idirnáisiúnú',
            'error.title' => 'Earráid OPUS',
            'error.request_failed' => 'Theip ar an iarratas.',
        ],
        'it' => [
            'language' => 'Lingua',
            'menu.home' => 'Home',
            'menu.architecture' => 'Architettura',
            'menu.router' => 'Router',
            'menu.modules' => 'Moduli',
            'menu.controllers' => 'Controller',
            'menu.views' => 'Viste',
            'menu.models' => 'Modelli',
            'menu.i18n' => 'Internazionalizzazione',
            'error.title' => 'Errore OPUS',
            'error.request_failed' => 'La richiesta non è riuscita.',
        ],
        'lv' => [
            'language' => 'Valoda',
            'menu.home' => 'Sākums',
            'menu.architecture' => 'Arhitektūra',
            'menu.router' => 'Maršrutētājs',
            'menu.modules' => 'Moduļi',
            'menu.controllers' => 'Kontrolleri',
            'menu.views' => 'Skati',
            'menu.models' => 'Modeļi',
            'menu.i18n' => 'Internacionalizācija',
            'error.title' => 'OPUS kļūda',
            'error.request_failed' => 'Pieprasījums neizdevās.',
        ],
        'lt' => [
            'language' => 'Kalba',
            'menu.home' => 'Pradžia',
            'menu.architecture' => 'Architektūra',
            'menu.router' => 'Maršrutizatorius',
            'menu.modules' => 'Moduliai',
            'menu.controllers' => 'Valdikliai',
            'menu.views' => 'Rodiniai',
            'menu.models' => 'Modeliai',
            'menu.i18n' => 'Internacionalizavimas',
            'error.title' => 'OPUS klaida',
            'error.request_failed' => 'Užklausa nepavyko.',
        ],
        'mt' => [
            'language' => 'Lingwa',
            'menu.home' => 'Paġna ewlenija',
            'menu.architecture' => 'Arkitettura',
            'menu.router' => 'Router',
            'menu.modules' => 'Moduli',
            'menu.controllers' => 'Kontrolluri',
            'menu.views' => 'Veduti',
            'menu.models' => 'Mudelli',
            'menu.i18n' => 'Internazzjonalizzazzjoni',
            'error.title' => 'Żball OPUS',
            'error.request_failed' => 'It-talba falliet.',
        ],
        'pl' => [
            'language' => 'Język',
            'menu.home' => 'Strona główna',
            'menu.architecture' => 'Architektura',
            'menu.router' => 'Router',
            'menu.modules' => 'Moduły',
            'menu.controllers' => 'Kontrolery',
            'menu.views' => 'Widoki',
            'menu.models' => 'Modele',
            'menu.i18n' => 'Internacjonalizacja',
            'error.title' => 'Błąd OPUS',
            'error.request_failed' => 'Żądanie nie powiodło się.',
        ],
        'pt' => [
            'language' => 'Idioma',
            'menu.home' => 'Início',
            'menu.architecture' => 'Arquitetura',
            'menu.router' => 'Encaminhador',
            'menu.modules' => 'Módulos',
            'menu.controllers' => 'Controladores',
            'menu.views' => 'Vistas',
            'menu.models' => 'Modelos',
            'menu.i18n' => 'Internacionalização',
            'error.title' => 'Erro do OPUS',
            'error.request_failed' => 'O pedido falhou.',
        ],
        'ro' => [
            'language' => 'Limbă',
            'menu.home' => 'Acasă',
            'menu.architecture' => 'Arhitectură',
            'menu.router' => 'Router',
            'menu.modules' => 'Module',
            'menu.controllers' => 'Controlere',
            'menu.views' => 'Vizualizări',
            'menu.models' => 'Modele',
            'menu.i18n' => 'Internaționalizare',
            'error.title' => 'Eroare OPUS',
            'error.request_failed' => 'Solicitarea a eșuat.',
        ],
        'sk' => [
            'language' => 'Jazyk',
            'menu.home' => 'Domov',
            'menu.architecture' => 'Architektúra',
            'menu.router' => 'Smerovač',
            'menu.modules' => 'Moduly',
            'menu.controllers' => 'Radiče',
            'menu.views' => 'Zobrazenia',
            'menu.models' => 'Modely',
            'menu.i18n' => 'Internacionalizácia',
            'error.title' => 'Chyba OPUS',
            'error.request_failed' => 'Požiadavka zlyhala.',
        ],
        'sl' => [
            'language' => 'Jezik',
            'menu.home' => 'Domov',
            'menu.architecture' => 'Arhitektura',
            'menu.router' => 'Usmerjevalnik',
            'menu.modules' => 'Moduli',
            'menu.controllers' => 'Krmilniki',
            'menu.views' => 'Pogledi',
            'menu.models' => 'Modeli',
            'menu.i18n' => 'Internacionalizacija',
            'error.title' => 'Napaka OPUS',
            'error.request_failed' => 'Zahteva ni uspela.',
        ],
        'es' => [
            'language' => 'Idioma',
            'menu.home' => 'Inicio',
            'menu.architecture' => 'Arquitectura',
            'menu.router' => 'Enrutador',
            'menu.modules' => 'Módulos',
            'menu.controllers' => 'Controladores',
            'menu.views' => 'Vistas',
            'menu.models' => 'Modelos',
            'menu.i18n' => 'Internacionalización',
            'error.title' => 'Error de OPUS',
            'error.request_failed' => 'La solicitud ha fallado.',
        ],
        'sv' => [
            'language' => 'Språk',
            'menu.home' => 'Hem',
            'menu.architecture' => 'Arkitektur',
            'menu.router' => 'Router',
            'menu.modules' => 'Moduler',
            'menu.controllers' => 'Styrenheter',
            'menu.views' => 'Vyer',
            'menu.models' => 'Modeller',
            'menu.i18n' => 'Internationalisering',
            'error.title' => 'OPUS-fel',
            'error.request_failed' => 'Begäran misslyckades.',
        ],
        'uk' => [
            'language' => 'Мова',
            'menu.home' => 'Головна',
            'menu.architecture' => 'Архітектура',
            'menu.router' => 'Маршрутизатор',
            'menu.modules' => 'Модулі',
            'menu.controllers' => 'Контролери',
            'menu.views' => 'Подання',
            'menu.models' => 'Моделі',
            'menu.i18n' => 'Інтернаціоналізація',
            'error.title' => 'Помилка OPUS',
            'error.request_failed' => 'Не вдалося виконати запит.',
        ],
    ];

    /** @var array<string,string> */
    private const MODULE_SUBTITLE_PREFIXES = [
        'bg' => 'OPUS модул, управляван от FSM: ',
        'hr' => 'OPUS modul kojim upravlja FSM: ',
        'cs' => 'Modul OPUS řízený FSM: ',
        'da' => 'FSM-styret OPUS-modul: ',
        'nl' => 'Door FSM aangestuurde OPUS-module: ',
        'en' => 'FSM-driven OPUS module: ',
        'et' => 'FSM-i juhitud OPUS-moodul: ',
        'fi' => 'FSM-ohjattu OPUS-moduuli: ',
        'fr' => 'Module OPUS piloté par FSM : ',
        'de' => 'FSM-gesteuertes OPUS-Modul: ',
        'el' => 'Μονάδα OPUS ελεγχόμενη από FSM: ',
        'hu' => 'FSM által vezérelt OPUS-modul: ',
        'ga' => 'Modúl OPUS arna rialú ag FSM: ',
        'it' => 'Modulo OPUS pilotato da FSM: ',
        'lv' => 'FSM vadīts OPUS modulis: ',
        'lt' => 'FSM valdomas OPUS modulis: ',
        'mt' => 'Modulu OPUS immexxi minn FSM: ',
        'pl' => 'Moduł OPUS sterowany przez FSM: ',
        'pt' => 'Módulo OPUS controlado por FSM: ',
        'ro' => 'Modul OPUS controlat de FSM: ',
        'sk' => 'Modul OPUS riadený FSM: ',
        'sl' => 'Modul OPUS, ki ga upravlja FSM: ',
        'es' => 'Módulo OPUS controlado por FSM: ',
        'sv' => 'FSM-styrd OPUS-modul: ',
        'uk' => 'Модуль OPUS, керований FSM: ',
    ];

    /** @var array<string,string> */
    private const LOGIN_LABELS = [
        'bg' => 'Вход', 'hr' => 'Prijava', 'cs' => 'Přihlášení',
        'da' => 'Log ind', 'nl' => 'Aanmelden', 'en' => 'Sign in',
        'et' => 'Logi sisse', 'fi' => 'Kirjaudu', 'fr' => 'Connexion',
        'de' => 'Anmelden', 'el' => 'Σύνδεση', 'hu' => 'Bejelentkezés',
        'ga' => 'Sínigh isteach', 'it' => 'Accesso', 'lv' => 'Pieteikties',
        'lt' => 'Prisijungti', 'mt' => 'Idħol', 'pl' => 'Logowanie',
        'pt' => 'Iniciar sessão', 'ro' => 'Autentificare',
        'sk' => 'Prihlásenie', 'sl' => 'Prijava', 'es' => 'Acceso',
        'sv' => 'Logga in', 'uk' => 'Вхід',
    ];

    /** @var list<string> */
    private const PROFILES = [
        self::PROFILE_FRONTEND,
        self::PROFILE_BACKEND,
        self::PROFILE_FULLSTACK,
    ];

    private function __construct(
        private readonly string $siteId,
        private readonly string $profile,
        private readonly array $blueprint
    ) {
    }

    public static function forSite(
        string $siteId,
        string $profile = self::PROFILE_FULLSTACK,
        array $blueprint = []
    ): self {
        $siteId = trim(strtolower($siteId));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $siteId) !== 1) {
            throw new \InvalidArgumentException('OPUS_APPLICATION_ID_INVALID:' . $siteId);
        }
        $profile = self::normalizeProfile($profile);
        return new self(
            $siteId,
            $profile,
            self::normalizeBlueprint($blueprint)
        );
    }

    public function profile(): string
    {
        return $this->profile;
    }

    /** @return list<string> */
    public static function profiles(): array
    {
        return self::PROFILES;
    }

    public function rootRelativePath(): string
    {
        return 'sites/' . $this->siteId;
    }

    /** @return list<ScaffoldEntry> */
    public function entries(): array
    {
        if ($this->profile === self::PROFILE_BACKEND) {
            return $this->backendEntries();
        }

        $site = $this->siteId;
        $directories = [
            "sites/{$site}/config",
            "sites/{$site}/application",
            "sites/{$site}/application/default",
            "sites/{$site}/application/default/helpers",
            "sites/{$site}/application/default/layouts",
            "sites/{$site}/application/default/local",
            "sites/{$site}/application/default/models",
            "sites/{$site}/application/default/navigation",
            "sites/{$site}/application/default/templates",
            "sites/{$site}/application/default/templates/components",
            "sites/{$site}/application/default/views",
            "sites/{$site}/www",
            "sites/{$site}/www/asset",
            "sites/{$site}/www/asset/css",
            "sites/{$site}/www/asset/js",
            "sites/{$site}/www/asset/themes",
            "sites/{$site}/www/asset/themes/starter",
            "sites/{$site}/www/asset/themes/starter/css",
            "sites/{$site}/www/asset/themes/starter/js",
            "sites/{$site}/www/asset/themes/starter/img",
            "sites/{$site}/www/asset/vendor",
            "sites/{$site}/var",
            "sites/{$site}/var/logs",
            "sites/{$site}/var/profiler",
        ];
        if ($this->blueprint['security']['provider'] === 'local-password') {
            $directories[] = "sites/{$site}/var/auth";
        }
        foreach ($this->modules() as $module) {
            foreach (['', '/acl', '/helpers', '/javascript', '/local', '/models', '/templates', '/views'] as $suffix) {
                $directories[] = "sites/{$site}/application/{$module}{$suffix}";
            }
        }

        $entries = array_map(
            static fn (string $directory): ScaffoldEntry => ScaffoldEntry::directory($directory),
            array_values(array_unique($directories))
        );

        $files = [
            "sites/{$site}/opus-site.json" => $this->json([
                'site_id' => $site,
                'contract' => 'OPUS_SITE_STANDARD_CONTRACT_CORE',
                'dispatch_model' => 'fsm-module-first',
                'application_profile' => $this->profile,
            ]),
            "sites/{$site}/config/site.json" => $this->json($this->siteConfig()),
            "sites/{$site}/config/routes.json" => $this->json($this->routesConfig()),
            "sites/{$site}/config/menu.json" => $this->json($this->menuConfig()),
            "sites/{$site}/config/application.fsm.json" => $this->json($this->fsmConfig()),
            "sites/{$site}/config/rubrics.json" => $this->json([
                'contract' => 'OPUS_RUBRIC_REGISTRY_V1',
                'rubrics' => [],
            ]),
            "sites/{$site}/config/acl.json" => $this->json($this->aclConfig()),
            "sites/{$site}/config/sso.json" => $this->json($this->ssoConfig()),
            "sites/{$site}/application/default/bootstrap.php" => $this->bootstrap(),
            "sites/{$site}/application/default/ApplicationInterface.php" => $this->applicationInterface(),
            "sites/{$site}/application/default/Application.php" => $this->applicationClass(),
            "sites/{$site}/application/default/layouts/layout.score" => $this->layoutTemplate(),
            "sites/{$site}/application/default/templates/error.score" => '<section class="opus-card opus-error" role="alert"><h2>{{ error.title }}</h2><p>{{ error.message }}</p><code>{{ error.code }}</code></section>' . "\n",
            "sites/{$site}/application/default/templates/components/header.score" => '<header class="opus-header"><h1>{{ site.name }}</h1><nav class="opus-menu">{{{ common.menu }}}</nav></header>' . "\n",
            "sites/{$site}/application/default/templates/components/footer.score" => '<footer class="opus-footer">{{ site.name }}</footer>' . "\n",
            "sites/{$site}/application/default/templates/components/menu-item.score" => '<a class="{{ menu_item.active_class }}" href="{{ menu_item.path }}">{{ menu_item.label }}</a>' . "\n",
            "sites/{$site}/application/default/templates/components/stylesheet.score" => '<link rel="stylesheet" href="{{ asset.href }}">' . "\n",
            "sites/{$site}/application/default/templates/components/script.score" => '<script src="{{ asset.src }}" defer></script>' . "\n",
            "sites/{$site}/application/default/navigation/menu.json" => $this->json($this->menuConfig()),
            "sites/{$site}/www/asset/css/default.css" => $this->defaultCss(),
            "sites/{$site}/www/asset/themes/starter/css/theme.css" => "body.opus-site{--opus-theme:starter}\n",
            "sites/{$site}/www/index.php" => $this->frontController(),
        ];
        if ($this->profile === self::PROFILE_FULLSTACK) {
            $files["sites/{$site}/config/fullstack.correlation.json"] =
                $this->json($this->fullstackCorrelationConfig());
        }
        if ($this->blueprint['security']['initial_users'] !== []) {
            $provider = (string) $this->blueprint['security']['provider'];
            $localPassword = $provider === 'local-password';
            $onboarding = [
                'contract' => 'OPUS_SECURITY_ONBOARDING_V1',
                'provider' => $provider,
                'identities' => array_map(
                    fn (string $subject): array => [
                        'subject' => $subject,
                        'roles' => [$this->blueprint['security'][
                            'initial_user_role'
                        ]],
                        'status' => $localPassword
                            ? 'password-setup-required'
                            : 'active',
                    ],
                    $this->blueprint['security']['initial_users']
                ),
                'secrets_versioned' => false,
            ];
            if ($localPassword) {
                $onboarding['runtime_store'] = 'var/auth/local-users.json';
            }
            $files["sites/{$site}/config/security.onboarding.json"] =
                $this->json($onboarding);
        }

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $files["sites/{$site}/application/default/local/{$locale}.json"] = $this->json(
                $this->defaultCatalog($locale)
            );
        }
        foreach ($this->modules() as $module) {
            $files["sites/{$site}/application/{$module}/templates/index.score"] =
                $this->moduleTemplate($module);
            $files["sites/{$site}/application/{$module}/views/index.php"] = $this->viewModel($module);
            $files["sites/{$site}/application/{$module}/acl/policy.json"] = $this->json([
                'contract' => 'OPUS_MODULE_ACL_POLICY_V1',
                'resource' => $module,
                'default' => 'deny',
                'open' => $module === 'home'
                    ? $this->blueprint['security']['home_roles']
                    : ['everyone'],
            ]);
            foreach (self::SUPPORTED_LOCALES as $locale) {
                $files["sites/{$site}/application/{$module}/local/{$locale}.json"] = $this->json(
                    $this->moduleCatalog($module, $locale)
                );
            }
        }

        foreach ($files as $path => $content) {
            $entries[] = ScaffoldEntry::file($path, $content);
        }
        return $entries;
    }

    /** @return array<string,mixed> */
    private function siteConfig(): array
    {
        return [
            'site_id' => $this->siteId,
            'site_name' => 'OPUS ' . $this->siteId,
            'role' => 'generated-opus-application',
            'kind' => $this->profile,
            'status' => 'generated',
            'blueprint' => 'opus-' . $this->profile,
            'generated_by' => 'composer',
            'contract' => 'OPUS_SITE_STANDARD_CONTRACT_CORE',
            'application_profile' => [
                'contract' => 'OPUS_APPLICATION_PROFILE_V1',
                'type' => $this->profile,
                'capabilities' => $this->profileCapabilities(),
            ],
            'creation_blueprint' => $this->blueprint,
            'default_locale' => self::FALLBACK_LOCALE,
            'locales' => self::SUPPORTED_LOCALES,
            'locale_negotiation' => [
                'contract' => 'OPUS_BROWSER_LOCALE_NEGOTIATION_V1',
                'strategy' => 'accept-language',
                'explicit_route_locale' => true,
                'fallback_locale' => self::FALLBACK_LOCALE,
                'fallback_diagnostic' => true,
            ],
            'diagnostics' => [
                'contract' => 'OPUS_APPLICATION_DIAGNOSTICS_V1',
                'logger' => [
                    'required' => true,
                    'file' => 'var/logs/' . $this->siteId . '.log',
                ],
                'profiler' => [
                    'required' => true,
                    'storage' => 'var/profiler',
                    'trace_id' => 'generated',
                ],
            ],
            'theme' => 'starter',
            'application_root' => 'application',
            'default_root' => 'application/default',
            'application_fsm' => 'config/application.fsm.json',
            'dispatch_model' => 'fsm-module-first',
            'public_root' => 'www',
            'asset_root' => 'www/asset',
            'navigation' => ['fsm' => 'config/application.fsm.json'],
            'runtime' => [
                'contract' => 'OPUS_APPLICATION_SINGLETON_V1',
                'architecture' => 'singleton',
                'class' => $this->applicationClassName(),
                'file' => 'application/default/Application.php',
                'bootstrap' => 'application/default/bootstrap.php',
                'entrypoint' => 'www/index.php',
                'factory' => 'instance',
                'runner' => 'run',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function routesConfig(): array
    {
        $routes = [];
        foreach ($this->modules() as $index => $module) {
            $routes[] = [
                'id' => $module . '.index',
                'path' => $module === 'home' ? '/' : '/' . $module,
                'state' => $module,
                'module' => $module,
                'action' => 'index',
                'template' => $module . '/templates/index.score',
                'view' => $module . '/views/index.php',
                'label' => 'menu.' . $module,
                'title_key' => 'page.title',
                'subtitle_key' => 'page.subtitle',
                'acl' => $module,
                'fsm_state' => $module,
                'dispatch_action' => 'render_route',
                'show_in_menu' => $module === 'home',
                'order' => ($index + 1) * 10,
            ];
        }
        return [
            'contract' => 'OPUS_ROUTE_REGISTRY_V1',
            'dispatch_model' => 'fsm-module-first',
            'routes' => $routes,
        ];
    }

    /** @return array<string,mixed> */
    private function menuConfig(): array
    {
        return [
            'contract' => 'OPUS_MENU_REGISTRY_V1',
            'items' => array_map(
                static fn (string $module): array => [
                    'route' => $module . '.index',
                    'label' => 'menu.' . $module,
                ],
                $this->modules()
            ),
        ];
    }

    /** @return array<string,mixed> */
    private function fsmConfig(): array
    {
        $states = [];
        $transitions = [];
        foreach ($this->modules() as $module) {
            $states[] = [
                'id' => $module,
                'module' => $module,
                'route' => $module === 'home' ? '/' : '/' . $module,
                'title_key' => 'menu.' . $module,
                'summary_key' => 'page.subtitle',
                'navigation' => ['label' => 'menu.' . $module],
            ];
            $transitions[] = [
                'id' => 'open.' . $module,
                'from' => '*',
                'signal' => 'open_' . $module,
                'next_state' => $module,
                'guards' => ['route_exists'],
                'actions' => ['render_route'],
            ];
        }
        return [
            'contract' => 'OPUS_APPLICATION_FSM_V1',
            'name' => $this->siteId . '.application',
            'site_id' => $this->siteId,
            'initial_state' => 'home',
            'states' => $states,
            'transitions' => $transitions,
        ];
    }

    /** @return array<string,mixed> */
    private function aclConfig(): array
    {
        $roles = $this->blueprint['security']['roles'];
        $homeRoles = $this->blueprint['security']['home_roles'];
        return [
            'contract' => 'OPUS_GENERATED_APPLICATION_ACL_V1',
            'default' => 'deny',
            'roles' => $roles,
            'permissions' => $this->blueprint['security']['permissions'],
            'policies' => [
                'home' => ['roles' => $homeRoles],
                'login' => ['roles' => ['everyone']],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function ssoConfig(): array
    {
        $provider = (string) $this->blueprint['security']['provider'];
        $login = (bool) $this->blueprint['security']['login_page'];
        return [
            'contract' => 'OPUS_GENERATED_APPLICATION_SSO_V1',
            'session_name' => 'OPUS_' . strtoupper(str_replace('-', '_', $this->siteId)),
            'session_identity_key' => 'opus_identity',
            'authentication_required' => (bool) $this->blueprint['security']['authentication_required'],
            'login_page' => $login,
            'default_provider' => $provider,
            'providers' => [
                'session' => ['enabled' => $provider === 'session'],
                'local-password' => [
                    'enabled' => $provider === 'local-password',
                    'runtime_store' => 'var/auth/local-users.json',
                    'secrets_versioned' => false,
                ],
                'auth0-proxy' => [
                    'enabled' => $provider === 'auth0-proxy',
                    'trusted_proxy_addresses' => ['127.0.0.1', '::1'],
                    'proxy_secret_env' => 'OPUS_AUTH0_PROXY_SECRET',
                    'subject_header' => 'HTTP_X_OPUS_AUTH0_SUBJECT',
                    'roles_header' => 'HTTP_X_OPUS_AUTH0_ROLES',
                    'secret_header' => 'HTTP_X_OPUS_PROXY_SECRET',
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function defaultCatalog(string $locale): array
    {
        if (!array_key_exists($locale, self::DEFAULT_MESSAGES)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_LOCALE_UNSUPPORTED:' . $locale
            );
        }

        return [
            'contract' => 'OPUS_I18N_CATALOG_V1',
            'locale' => $locale,
            'scope' => 'default',
            'messages' => array_replace(
                self::DEFAULT_MESSAGES[$locale],
                ['menu.login' => self::LOGIN_LABELS[$locale]]
            ),
        ];
    }

    /** @return array<string,mixed> */
    private function moduleCatalog(string $module, string $locale): array
    {
        if (!array_key_exists($locale, self::DEFAULT_MESSAGES)
            || !array_key_exists($locale, self::MODULE_SUBTITLE_PREFIXES)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_LOCALE_UNSUPPORTED:' . $locale
            );
        }

        $title = $module === 'login'
            ? self::LOGIN_LABELS[$locale]
            : (self::DEFAULT_MESSAGES[$locale]['menu.' . $module] ?? null);
        if (!is_string($title)) {
            throw new \RuntimeException(
                'OPUS_APPLICATION_MODULE_TRANSLATION_MISSING:'
                . $locale
                . ':'
                . $module
            );
        }

        return [
            'contract' => 'OPUS_I18N_CATALOG_V1',
            'locale' => $locale,
            'scope' => $module,
            'messages' => [
                'page.title' => $title,
                'page.subtitle' => self::MODULE_SUBTITLE_PREFIXES[$locale]
                    . $module,
                'auth.username' => 'Username',
                'auth.password' => 'Password',
                'auth.submit' => self::LOGIN_LABELS[$locale],
                'auth.error' => 'Authentication failed.',
            ],
        ];
    }

    private function viewModel(string $module): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'module' => " . var_export($module, true) . ",\n    'page' => ['title' => '', 'subtitle' => ''],\n];\n";
    }

    private function moduleTemplate(string $module): string
    {
        if ($module !== 'login') {
            return '<section class="opus-card"><h2>{{ page.title }}</h2>'
                . '<p>{{ page.subtitle }}</p></section>' . "\n";
        }
        return '<section class="opus-card"><h2>{{ page.title }}</h2>'
            . '<p>{{ page.subtitle }}</p>'
            . '[[ if: auth.error ]]<p role="alert">[[ i18n: auth.error ]]</p>[[ endif ]]'
            . '<form method="post">'
            . '<label>[[ i18n: auth.username ]]<input name="username" autocomplete="username" required></label>'
            . '<label>[[ i18n: auth.password ]]<input name="password" type="password" autocomplete="current-password" required></label>'
            . '<button type="submit">[[ i18n: auth.submit ]]</button>'
            . '</form></section>' . "\n";
    }

    private function layoutTemplate(): string
    {
        return "<!doctype html>\n<html lang=\"{{ lang }}\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{ page.title }}</title>\n{{{ assets.css }}}\n</head>\n<body class=\"opus-site\">\n{{{ common.header }}}\n<main id=\"main-content\" class=\"opus-shell\">{{{ content }}}</main>\n{{{ common.footer }}}\n{{{ assets.js }}}\n</body>\n</html>\n";
    }

    private function defaultCss(): string
    {
        return "body.opus-site{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#eef3f8;color:#162336}.opus-header,.opus-footer{background:#24466d;color:#fff;padding:24px}.opus-shell{padding:24px;min-height:60vh}.opus-card{display:block;margin:12px 0;padding:16px;background:#fff;border:1px solid #d7e0eb;border-radius:12px}.opus-error{border-color:#a22}.opus-menu a{color:#fff;margin-right:12px}.is-active{font-weight:700}\n";
    }

    private function applicationClass(): string
    {
        $class = $this->applicationClassName();
        $source = <<<'PHP'
<?php
declare(strict_types=1);

use Opus\Application\Runtime\GeneratedSiteRuntime;
use Opus\Http\Response;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

final class {{APPLICATION_CLASS}} implements {{APPLICATION_CLASS}}Interface
{
    private static ?self $instance = null;
    private readonly GeneratedSiteRuntime $runtime;
    private readonly Logger $logger;
    private readonly Profiler $profiler;

    private function __construct(private readonly string $siteRoot)
    {
        $this->runtime = new GeneratedSiteRuntime($siteRoot);
        $this->logger = new Logger(
            $siteRoot . '/var/logs',
            '{{LOG_FILE}}'
        );
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException(
                    'OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH'
                );
            }
            return self::$instance;
        }
        return self::$instance = new self($siteRoot);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException(
            'OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN'
        );
    }

    public function handle(): Response
    {
        $trace = $this->profiler->start();
        $traceId = $trace->getTraceId();
        $startedAt = microtime(true);
        $status = 'failed';

        try {
            $context = ['method' => $this->requestMethod()];
            $this->logger->info(
                'application.runtime',
                'request.received',
                $context,
                $traceId
            );
            $this->profiler->event(
                'application.runtime',
                'request.received',
                $context
            );

            $response = $this->runtime->handle();
            $status = 'completed';
            $durationMs = round(
                (microtime(true) - $startedAt) * 1000,
                3
            );
            $completed = ['duration_ms' => $durationMs];
            $this->logger->info(
                'application.runtime',
                'request.completed',
                $completed,
                $traceId
            );
            $this->profiler->event(
                'application.runtime',
                'request.completed',
                $completed
            );

            return $response;
        } catch (Throwable $error) {
            $durationMs = round(
                (microtime(true) - $startedAt) * 1000,
                3
            );
            $failed = [
                'duration_ms' => $durationMs,
                'error_code' => $this->safeErrorCode($error),
            ];
            $this->logger->error(
                'application.runtime',
                'request.failed',
                $failed,
                $traceId
            );
            $this->profiler->event(
                'application.runtime',
                'request.failed',
                $failed
            );
            throw $error;
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    3
                ),
            ]);
        }
    }

    public function run(): void
    {
        $this->handle()->send();
    }

    private function requestMethod(): string
    {
        $method = strtoupper(trim((string) (
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        )));

        return preg_match('/^[A-Z]{3,16}$/', $method) === 1
            ? $method
            : 'UNKNOWN';
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());

        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OPUS_APPLICATION_RUNTIME_FAILED';
    }
}

PHP;
        return str_replace(
            ['{{APPLICATION_CLASS}}', '{{LOG_FILE}}'],
            [$class, $this->siteId . '.log'],
            $source
        );
    }

    private function bootstrap(): string
    {
        $class = $this->applicationClassName();
        $source = <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit;
}
require_once $autoload;
require_once __DIR__ . '/ApplicationInterface.php';
require_once __DIR__ . '/Application.php';

{{APPLICATION_CLASS}}::instance($siteRoot)->run();
PHP;
        return str_replace('{{APPLICATION_CLASS}}', $class, $source);
    }

    private function applicationInterface(): string
    {
        $class = $this->applicationClassName();
        return "<?php\ndeclare(strict_types=1);\n\ninterface {$class}Interface extends\n"
            . "    \\Opus\\Framework\\OpusFrameworkComponentInterface,\n"
            . "    \\Opus\\Framework\\OpusExceptionAwareInterface,\n"
            . "    \\Opus\\Framework\\OpusProfilerAwareInterface,\n"
            . "    \\Opus\\Framework\\OpusSelfDocumentingInterface\n"
            . "{\n    public static function instance(string \$siteRoot): self;\n"
            . "    public function run(): void;\n}\n";
    }

    /** @return list<ScaffoldEntry> */
    private function backendEntries(): array
    {
        $site = $this->siteId;
        $directories = [
            "sites/{$site}/config",
            "sites/{$site}/application",
            "sites/{$site}/application/default",
            "sites/{$site}/application/default/local",
            "sites/{$site}/application/api",
            "sites/{$site}/application/api/controllers",
            "sites/{$site}/www",
            "sites/{$site}/var",
            "sites/{$site}/var/logs",
            "sites/{$site}/var/profiler",
            "sites/{$site}/var/profiler/rest",
        ];
        $entries = array_map(
            static fn (string $path): ScaffoldEntry => ScaffoldEntry::directory($path),
            $directories
        );

        $siteConfig = $this->siteConfig();
        $siteConfig['application_surface'] = [
            'contract' => 'OPUS_APPLICATION_SURFACE_V1',
            'type' => 'backend',
        ];
        unset($siteConfig['theme'], $siteConfig['asset_root']);
        $controller = $this->backendControllerClassName();
        $files = [
            "sites/{$site}/opus-site.json" => $this->json([
                'site_id' => $site,
                'contract' => 'OPUS_SITE_STANDARD_CONTRACT_CORE',
                'dispatch_model' => 'fsm-module-first',
                'application_profile' => self::PROFILE_BACKEND,
            ]),
            "sites/{$site}/config/site.json" => $this->json($siteConfig),
            "sites/{$site}/config/routes.json" => $this->json([
                'contract' => 'OPUS_ROUTE_REGISTRY_V1',
                'dispatch_model' => 'fsm-module-first',
                'routes' => [[
                    'id' => 'api.dispatch',
                    'path' => '/api/v1/{*resource}',
                    'state' => 'api',
                    'module' => 'api',
                    'action' => 'dispatch',
                    'acl' => 'api',
                    'fsm_state' => 'api',
                    'dispatch_action' => 'dispatch_rest',
                    'show_in_menu' => false,
                    'order' => 10,
                ]],
            ]),
            "sites/{$site}/config/application.fsm.json" => $this->json([
                'contract' => 'OPUS_APPLICATION_FSM_V1',
                'name' => $site . '.application',
                'site_id' => $site,
                'initial_state' => 'api',
                'states' => [[
                    'id' => 'api',
                    'module' => 'api',
                    'route' => '/api/v1/{*resource}',
                ]],
                'transitions' => [[
                    'id' => 'dispatch.api',
                    'from' => '*',
                    'signal' => 'dispatch_api',
                    'next_state' => 'api',
                    'guards' => ['route_exists'],
                    'actions' => ['dispatch_rest'],
                ]],
            ]),
            "sites/{$site}/config/acl.json" => $this->json($this->aclConfig()),
            "sites/{$site}/config/sso.json" => $this->json($this->ssoConfig()),
            "sites/{$site}/config/backend.rest.json" => $this->json(
                $this->backendRestConfig()
            ),
            "sites/{$site}/config/backend.operations.json" => $this->json(
                $this->backendOperationsConfig()
            ),
            "sites/{$site}/application/default/ApplicationInterface.php" => $this->applicationInterface(),
            "sites/{$site}/application/default/bootstrap.php" => $this->backendBootstrap(),
            "sites/{$site}/application/default/Application.php" => $this->backendApplicationClass(),
            "sites/{$site}/application/api/controllers/{$controller}Interface.php" => $this->backendControllerInterface(),
            "sites/{$site}/application/api/controllers/BackendApiController.php" => $this->backendControllerClass(),
            "sites/{$site}/www/index.php" => $this->frontController(),
        ];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $files["sites/{$site}/application/default/local/{$locale}.json"] =
                $this->json($this->defaultCatalog($locale));
        }
        foreach ($files as $path => $content) {
            $entries[] = ScaffoldEntry::file($path, $content);
        }
        return $entries;
    }

    /** @return array<string,mixed> */
    private function backendRestConfig(): array
    {
        $secret = strtoupper(str_replace('-', '_', $this->siteId));
        return [
            'contract' => 'OPUS_REST_API_SERVER_CONFIG_V1',
            'application_id' => $this->siteId,
            'base_path' => '/api/v1',
            'operation_catalog' => 'sites/' . $this->siteId
                . '/config/backend.operations.json',
            'composer_command' => ['@in-process'],
            'timeout_seconds' => 120,
            'max_output_bytes' => 2097152,
            'resources' => [[
                'method' => 'GET',
                'path' => '/api/v1/application/validation',
                'operation' => 'site.validate',
                'parameters' => ['site_id' => $this->siteId],
                'success_status' => 200,
            ]],
            'supported_locales' => self::SUPPORTED_LOCALES,
            'default_locale' => self::FALLBACK_LOCALE,
            'authentication' => [
                'mode' => 'environment_hmac',
                'service' => $this->siteId . '-front',
                'token_env' => 'OPUS_' . $secret . '_BACKEND_TOKEN',
                'hmac_env' => 'OPUS_' . $secret . '_BACKEND_HMAC',
                'minimum_secret_length' => 32,
                'max_clock_skew_seconds' => 60,
                'delegated_roles' => $this->blueprint['security']['roles'],
                'delegated_providers' => ['session', 'local-password', 'auth0-proxy'],
                'trusted_proxy_addresses' => ['127.0.0.1', '::1'],
                'proxy_secret_env' => 'OPUS_' . $secret . '_AUTH0_PROXY_SECRET',
            ],
            'fsm' => [
                'initial_state' => 'received',
                'transitions' => [
                    'received' => ['authenticated', 'failed'],
                    'authenticated' => ['authorized', 'failed'],
                    'authorized' => ['dispatching', 'failed'],
                    'dispatching' => ['succeeded', 'failed'],
                    'succeeded' => [],
                    'failed' => [],
                ],
            ],
            'diagnostics' => [
                'log_directory' => 'sites/' . $this->siteId . '/var/logs',
                'log_file' => $this->siteId . '.log',
                'profiler_directory' => 'sites/' . $this->siteId
                    . '/var/profiler/rest',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backendOperationsConfig(): array
    {
        return [
            'contract' => 'OPUS_REST_API_COMPOSER_OPERATION_CATALOG_V1',
            'operations' => [
                'site.validate' => [
                    'composer_script' => 'opus:validate-site',
                    'roles' => $this->blueprint['security']['roles'],
                    'arguments' => [[
                        'name' => 'site_id',
                        'required' => true,
                        'pattern' => '^[a-z][a-z0-9-]*$',
                        'max_length' => 64,
                    ]],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function fullstackCorrelationConfig(): array
    {
        return [
            'contract' => 'OPUS_FULLSTACK_REST_CORRELATION_V1',
            'transport' => 'secured-rest',
            'front_surface' => 'score',
            'back_surface' => 'php-rest-composer',
            'shared_directory_forbidden' => true,
            'trace_header' => 'X-Opus-Trace-Id',
            'request_header' => 'X-Opus-Rest-Nonce',
            'flow' => ['front', 'rest', 'back', 'composer', 'back', 'rest', 'front'],
        ];
    }

    private function backendControllerClassName(): string
    {
        return substr($this->applicationClassName(), 0, -11)
            . 'BackendApiController';
    }

    private function backendControllerInterface(): string
    {
        $class = $this->backendControllerClassName();
        return "<?php\ndeclare(strict_types=1);\n\ninterface {$class}Interface extends\n"
            . "    \\Opus\\Framework\\OpusFrameworkComponentInterface,\n"
            . "    \\Opus\\Framework\\OpusExceptionAwareInterface,\n"
            . "    \\Opus\\Framework\\OpusProfilerAwareInterface,\n"
            . "    \\Opus\\Framework\\OpusSelfDocumentingInterface\n"
            . "{\n    public function matchesCurrentRequest(): bool;\n"
            . "    public function run(): void;\n}\n";
    }

    private function backendControllerClass(): string
    {
        $class = $this->backendControllerClassName();
        $config = 'sites/' . $this->siteId . '/config/backend.rest.json';
        $source = <<<'PHP'
<?php
declare(strict_types=1);

use Opus\Api\Rest\RestServer;
use Opus\Http\Request;

final class {{CONTROLLER}} implements {{CONTROLLER}}Interface
{
    private ?Request $request = null;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
    }

    public function matchesCurrentRequest(): bool
    {
        return str_starts_with('/' . trim($this->request()->path, '/'), '/api/');
    }

    public function run(): void
    {
        RestServer::fromRoot($this->opusRoot, '{{CONFIG}}')
            ->handle($this->request())->send();
    }

    private function request(): Request
    {
        return $this->request ??= Request::fromGlobals($this->opusRoot);
    }
}
PHP;
        return str_replace(['{{CONTROLLER}}', '{{CONFIG}}'], [$class, $config], $source);
    }

    private function backendApplicationClass(): string
    {
        $class = $this->applicationClassName();
        $controller = $this->backendControllerClassName();
        $source = <<<'PHP'
<?php
declare(strict_types=1);

use Opus\Http\Response;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;

final class {{APPLICATION}} implements {{APPLICATION}}Interface
{
    private static ?self $instance = null;
    private readonly Logger $logger;
    private readonly Profiler $profiler;
    private readonly {{CONTROLLER}} $controller;

    private function __construct(private readonly string $siteRoot)
    {
        $this->logger = new Logger($siteRoot . '/var/logs', '{{LOG}}');
        $this->profiler = new Profiler($siteRoot . '/var/profiler');
        $this->controller = new {{CONTROLLER}}(
            $siteRoot,
            dirname(dirname($siteRoot))
        );
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException('OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH');
            }
            return self::$instance;
        }
        return self::$instance = new self($siteRoot);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN');
    }

    public function run(): void
    {
        $incoming = trim((string) ($_SERVER['HTTP_X_OPUS_TRACE_ID'] ?? ''));
        $trace = $this->profiler->start(
            preg_match('/^[a-f0-9]{16,64}$/', $incoming) === 1 ? $incoming : null
        );
        $traceId = $trace->getTraceId();
        $status = 'failed';
        try {
            if (!$this->controller->matchesCurrentRequest()) {
                throw new RuntimeException('OPUS_BACKEND_ROUTE_FORBIDDEN');
            }
            $this->logger->info('application.backend', 'request.received', [], $traceId);
            $this->controller->run();
            $status = 'completed';
        } catch (Throwable $error) {
            $this->logger->error('application.backend', 'request.failed', [
                'error_code' => preg_match('/^[A-Z0-9_:-]{3,240}$/', $error->getMessage()) === 1
                    ? $error->getMessage() : 'OPUS_BACKEND_RUNTIME_FAILED',
            ], $traceId);
            Response::json([
                'contract' => 'OPUS_BACKEND_ERROR_V1',
                'status' => 'failed',
                'trace_id' => $traceId,
            ], 500)->send();
        } finally {
            $this->profiler->stop([
                'component' => self::class,
                'status' => $status,
                'trace_id' => $traceId,
            ]);
        }
    }
}
PHP;
        return str_replace(
            ['{{APPLICATION}}', '{{CONTROLLER}}', '{{LOG}}'],
            [$class, $controller, $this->siteId . '.log'],
            $source
        );
    }

    private function backendBootstrap(): string
    {
        $application = $this->applicationClassName();
        $controller = $this->backendControllerClassName();
        $source = <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit;
}
require_once $autoload;
require_once dirname(__DIR__) . '/api/controllers/{{CONTROLLER}}Interface.php';
require_once dirname(__DIR__) . '/api/controllers/BackendApiController.php';
require_once __DIR__ . '/ApplicationInterface.php';
require_once __DIR__ . '/Application.php';

{{APPLICATION}}::instance($siteRoot)->run();
PHP;
        return str_replace(
            ['{{APPLICATION}}', '{{CONTROLLER}}'],
            [$application, $controller],
            $source
        );
    }

    private function applicationClassName(): string
    {
        $parts = preg_split('/[^a-z0-9]+/i', $this->siteId) ?: [];
        $name = implode('', array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            array_filter($parts, static fn (string $part): bool => $part !== '')
        ));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \RuntimeException('OPUS_APPLICATION_CLASS_NAME_INVALID');
        }
        return $name . 'Application';
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );
    if (is_string($requestPath) && !str_contains($requestPath, "\0")) {
        $publicRoot = str_replace('\\', '/', __DIR__);
        $candidate = realpath(
            __DIR__ . '/' . ltrim(rawurldecode($requestPath), '/')
        );
        if (is_string($candidate)) {
            $candidate = str_replace('\\', '/', $candidate);
            if (str_starts_with($candidate, $publicRoot . '/')
                && is_file($candidate)) {
                return false;
            }
        }
    }
}

require dirname(__DIR__) . '/application/default/bootstrap.php';
PHP;
    }


    /** @return list<string> */
    private function modules(): array
    {
        return (bool) $this->blueprint['security']['login_page']
            ? ['home', 'login']
            : ['home'];
    }

    /** @return array{presentation:bool,api:bool,persistence:bool} */
    private function profileCapabilities(): array
    {
        return match ($this->profile) {
            self::PROFILE_FRONTEND => [
                'presentation' => true,
                'api' => false,
                'persistence' => false,
            ],
            self::PROFILE_BACKEND => [
                'presentation' => false,
                'api' => true,
                'persistence' => true,
            ],
            self::PROFILE_FULLSTACK => [
                'presentation' => true,
                'api' => true,
                'persistence' => true,
            ],
            default => throw new \LogicException(
                'OPUS_APPLICATION_PROFILE_UNREACHABLE:' . $this->profile
            ),
        };
    }

    private static function normalizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        if (!in_array($profile, self::PROFILES, true)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_PROFILE_INVALID:' . $profile
            );
        }
        return $profile;
    }

    /** @param array<string,mixed> $blueprint @return array<string,mixed> */
    private static function normalizeBlueprint(array $blueprint): array
    {
        if ($blueprint === []) {
            $blueprint = [
                'contract' => 'OPUS_SITE_CREATION_BLUEPRINT_V1',
                'security' => [
                    'authentication_required' => false,
                    'login_page' => false,
                    'provider' => 'session',
                    'roles' => ['admin'],
                    'permissions' => ['home:view'],
                    'home_roles' => ['everyone'],
                    'initial_users' => [],
                    'initial_user_role' => '',
                ],
            ];
        }
        if (($blueprint['contract'] ?? null)
            !== 'OPUS_SITE_CREATION_BLUEPRINT_V1') {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_CONTRACT_INVALID'
            );
        }
        $security = is_array($blueprint['security'] ?? null)
            ? $blueprint['security']
            : null;
        if (!is_array($security)
            || !is_bool($security['authentication_required'] ?? null)
            || !is_bool($security['login_page'] ?? null)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_SECURITY_INVALID'
            );
        }
        $provider = strtolower(trim((string) (
            $security['provider'] ?? ''
        )));
        if (!in_array(
            $provider,
            ['session', 'local-password', 'auth0-proxy'],
            true
        )) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_PROVIDER_INVALID'
            );
        }
        if (($security['login_page'] ?? false) === true
            && ($security['authentication_required'] ?? false) !== true) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_LOGIN_WITHOUT_AUTH'
            );
        }
        if ($provider === 'local-password'
            && ($security['login_page'] ?? false) !== true) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_LOCAL_LOGIN_REQUIRED'
            );
        }
        if (($security['login_page'] ?? false) === true
            && $provider !== 'local-password') {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_LOGIN_PROVIDER_INVALID'
            );
        }
        $roles = self::identifiers($security['roles'] ?? null, 'ROLES');
        $homeRoles = self::identifiers(
            $security['home_roles'] ?? null,
            'HOME_ROLES'
        );
        if (array_diff($homeRoles, array_merge($roles, ['everyone'])) !== []) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_HOME_ROLE_UNKNOWN'
            );
        }
        $permissions = self::permissions(
            $security['permissions'] ?? null
        );
        if (($security['authentication_required'] ?? false) !== true
            && (($security['login_page'] ?? false) === true
                || $provider !== 'session')) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_PUBLIC_PROVIDER_INVALID'
            );
        }
        if (($security['authentication_required'] ?? false) === true
            && in_array('everyone', $homeRoles, true)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_AUTH_HOME_ANONYMOUS'
            );
        }
        $users = self::identifiers(
            $security['initial_users'] ?? [],
            'INITIAL_USERS',
            true
        );
        $initialUserRole = strtolower(trim((string) (
            $security['initial_user_role'] ?? ''
        )));
        if ($users !== []
            && !in_array($initialUserRole, $roles, true)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_USER_ROLE_UNKNOWN'
            );
        }
        $security['provider'] = $provider;
        $security['roles'] = $roles;
        $security['permissions'] = $permissions;
        $security['home_roles'] = $homeRoles;
        $security['initial_users'] = $users;
        $security['initial_user_role'] =
            $users === [] ? '' : $initialUserRole;
        return [
            'contract' => 'OPUS_SITE_CREATION_BLUEPRINT_V1',
            'result' => 'minimal-home',
            'locales' => self::SUPPORTED_LOCALES,
            'security' => $security,
        ];
    }

    /** @return list<string> */
    private static function identifiers(
        mixed $value,
        string $field,
        bool $allowEmpty = false
    ): array {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_' . $field . '_INVALID'
            );
        }
        $result = [];
        foreach ($value as $identifier) {
            $identifier = strtolower(trim((string) $identifier));
            if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $identifier) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_APPLICATION_BLUEPRINT_' . $field . '_INVALID'
                );
            }
            $result[$identifier] = true;
        }
        if ($result === [] && !$allowEmpty) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_' . $field . '_EMPTY'
            );
        }
        return array_keys($result);
    }

    /** @return list<string> */
    private static function permissions(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_PERMISSIONS_INVALID'
            );
        }
        $result = [];
        foreach ($value as $permission) {
            $permission = strtolower(trim((string) $permission));
            if (preg_match(
                '/^[a-z][a-z0-9.-]{0,63}:[a-z][a-z0-9.-]{0,63}$/D',
                $permission
            ) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_APPLICATION_BLUEPRINT_PERMISSIONS_INVALID'
                );
            }
            $result[$permission] = true;
        }
        if ($result === []) {
            throw new \InvalidArgumentException(
                'OPUS_APPLICATION_BLUEPRINT_PERMISSIONS_EMPTY'
            );
        }
        return array_keys($result);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return Json::instance()->encode($data, true) . "\n";
    }
}
