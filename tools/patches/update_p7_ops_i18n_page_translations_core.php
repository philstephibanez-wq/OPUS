<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

if (!is_dir($publicDir)) {
    fwrite(STDERR, 'P7_OPS_PUBLIC_DIR_MISSING' . PHP_EOL);
    exit(1);
}

function p7tr_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        throw new RuntimeException('P7_PAGE_TRANSLATIONS_WRITE_FAILED: ' . $file);
    }
}

function p7tr_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('P7_PAGE_TRANSLATIONS_READ_FAILED: ' . $file);
    }
    return $source;
}

$languageSource = <<<'PHP'
<?php
declare(strict_types=1);

/**
 * P7_OPS_I18N_PAGE_TRANSLATIONS_CORE
 *
 * Real visible page-translation layer for OPUS OPS pages.
 * Scope: 24 official EU languages + Ukrainian.
 * Native URL rule: accents and native characters are preserved in readable slugs.
 * Backward compatibility markers: P7_OPS_LANGUAGE_SELECTOR_CORE, P7_OPS_I18N_NATIVE_URL_SLUGS_CORE.
 */

if (!function_exists('p7ops_h')) {
    function p7ops_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_language_options')) {
    function p7ops_language_options(): array
    {
        static $data = null;
        if ($data === null) {
            $decoded = json_decode(<<<'JSON'
{
  "bg": {
    "name": "Български",
    "english": "Bulgarian",
    "scope": "EU",
    "slug": "български"
  },
  "hr": {
    "name": "Hrvatski",
    "english": "Croatian",
    "scope": "EU",
    "slug": "hrvatski"
  },
  "cs": {
    "name": "Čeština",
    "english": "Czech",
    "scope": "EU",
    "slug": "čeština"
  },
  "da": {
    "name": "Dansk",
    "english": "Danish",
    "scope": "EU",
    "slug": "dansk"
  },
  "nl": {
    "name": "Nederlands",
    "english": "Dutch",
    "scope": "EU",
    "slug": "nederlands"
  },
  "en": {
    "name": "English",
    "english": "English",
    "scope": "EU",
    "slug": "english"
  },
  "et": {
    "name": "Eesti",
    "english": "Estonian",
    "scope": "EU",
    "slug": "eesti"
  },
  "fi": {
    "name": "Suomi",
    "english": "Finnish",
    "scope": "EU",
    "slug": "suomi"
  },
  "fr": {
    "name": "Français",
    "english": "French",
    "scope": "EU",
    "slug": "français"
  },
  "de": {
    "name": "Deutsch",
    "english": "German",
    "scope": "EU",
    "slug": "deutsch"
  },
  "el": {
    "name": "Ελληνικά",
    "english": "Greek",
    "scope": "EU",
    "slug": "ελληνικά"
  },
  "hu": {
    "name": "Magyar",
    "english": "Hungarian",
    "scope": "EU",
    "slug": "magyar"
  },
  "ga": {
    "name": "Gaeilge",
    "english": "Irish",
    "scope": "EU",
    "slug": "gaeilge"
  },
  "it": {
    "name": "Italiano",
    "english": "Italian",
    "scope": "EU",
    "slug": "italiano"
  },
  "lv": {
    "name": "Latviešu",
    "english": "Latvian",
    "scope": "EU",
    "slug": "latviešu"
  },
  "lt": {
    "name": "Lietuvių",
    "english": "Lithuanian",
    "scope": "EU",
    "slug": "lietuvių"
  },
  "mt": {
    "name": "Malti",
    "english": "Maltese",
    "scope": "EU",
    "slug": "malti"
  },
  "pl": {
    "name": "Polski",
    "english": "Polish",
    "scope": "EU",
    "slug": "polski"
  },
  "pt": {
    "name": "Português",
    "english": "Portuguese",
    "scope": "EU",
    "slug": "português"
  },
  "ro": {
    "name": "Română",
    "english": "Romanian",
    "scope": "EU",
    "slug": "română"
  },
  "sk": {
    "name": "Slovenčina",
    "english": "Slovak",
    "scope": "EU",
    "slug": "slovenčina"
  },
  "sl": {
    "name": "Slovenščina",
    "english": "Slovenian",
    "scope": "EU",
    "slug": "slovenščina"
  },
  "es": {
    "name": "Español",
    "english": "Spanish",
    "scope": "EU",
    "slug": "español"
  },
  "sv": {
    "name": "Svenska",
    "english": "Swedish",
    "scope": "EU",
    "slug": "svenska"
  },
  "uk": {
    "name": "Українська",
    "english": "Ukrainian",
    "scope": "UKRAINIAN",
    "slug": "українська"
  }
}
JSON, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }
}


if (!function_exists('p7ops_i18n_catalog')) {
    function p7ops_i18n_catalog(): array
    {
        static $data = null;
        if ($data === null) {
            $decoded = json_decode(<<<'JSON'
{
  "bg": {
    "language": "Език",
    "active_language": "Активен език: Български",
    "choose_language": "Изберете език",
    "dashboard": "Табло",
    "operations": "Операции",
    "command_center": "Команден център",
    "navigation": "Навигация",
    "diagnostics": "Диагностика",
    "health_hub": "Център за състояние"
  },
  "hr": {
    "language": "Jezik",
    "active_language": "Aktivni jezik: Hrvatski",
    "choose_language": "Odaberite jezik",
    "dashboard": "Nadzorna ploča",
    "operations": "Operacije",
    "command_center": "Zapovjedni centar",
    "navigation": "Navigacija",
    "diagnostics": "Dijagnostika",
    "health_hub": "Središte stanja"
  },
  "cs": {
    "language": "Jazyk",
    "active_language": "Aktivní jazyk: Čeština",
    "choose_language": "Vyberte jazyk",
    "dashboard": "Přehled",
    "operations": "Operace",
    "command_center": "Řídicí centrum",
    "navigation": "Navigace",
    "diagnostics": "Diagnostika",
    "health_hub": "Centrum stavu"
  },
  "da": {
    "language": "Sprog",
    "active_language": "Aktivt sprog: Dansk",
    "choose_language": "Vælg et sprog",
    "dashboard": "Dashboard",
    "operations": "Operationer",
    "command_center": "Kommandocenter",
    "navigation": "Navigation",
    "diagnostics": "Diagnostik",
    "health_hub": "Sundhedshub"
  },
  "nl": {
    "language": "Taal",
    "active_language": "Actieve taal: Nederlands",
    "choose_language": "Kies een taal",
    "dashboard": "Dashboard",
    "operations": "Operaties",
    "command_center": "Commandocentrum",
    "navigation": "Navigatie",
    "diagnostics": "Diagnostiek",
    "health_hub": "Gezondheidscentrum"
  },
  "en": {
    "language": "Language",
    "active_language": "Active language: English",
    "choose_language": "Choose a language",
    "dashboard": "Dashboard",
    "operations": "Operations",
    "command_center": "Command Center",
    "navigation": "Navigation",
    "diagnostics": "Diagnostics",
    "health_hub": "Health Hub"
  },
  "et": {
    "language": "Keel",
    "active_language": "Aktiivne keel: Eesti",
    "choose_language": "Valige keel",
    "dashboard": "Ülevaade",
    "operations": "Toimingud",
    "command_center": "Juhtimiskeskus",
    "navigation": "Navigeerimine",
    "diagnostics": "Diagnostika",
    "health_hub": "Tervisekeskus"
  },
  "fi": {
    "language": "Kieli",
    "active_language": "Aktiivinen kieli: Suomi",
    "choose_language": "Valitse kieli",
    "dashboard": "Koontinäkymä",
    "operations": "Toiminnot",
    "command_center": "Komentokeskus",
    "navigation": "Navigointi",
    "diagnostics": "Diagnostiikka",
    "health_hub": "Tilakeskus"
  },
  "fr": {
    "language": "Langue",
    "active_language": "Langue active : Français",
    "choose_language": "Choisir une langue",
    "dashboard": "Tableau de bord",
    "operations": "Opérations",
    "command_center": "Centre de commande",
    "navigation": "Navigation",
    "diagnostics": "Diagnostics",
    "health_hub": "Centre de santé"
  },
  "de": {
    "language": "Sprache",
    "active_language": "Aktive Sprache: Deutsch",
    "choose_language": "Sprache auswählen",
    "dashboard": "Übersicht",
    "operations": "Operationen",
    "command_center": "Befehlszentrale",
    "navigation": "Navigation",
    "diagnostics": "Diagnose",
    "health_hub": "Statuszentrale"
  },
  "el": {
    "language": "Γλώσσα",
    "active_language": "Ενεργή γλώσσα: Ελληνικά",
    "choose_language": "Επιλέξτε γλώσσα",
    "dashboard": "Πίνακας ελέγχου",
    "operations": "Λειτουργίες",
    "command_center": "Κέντρο εντολών",
    "navigation": "Πλοήγηση",
    "diagnostics": "Διαγνωστικά",
    "health_hub": "Κέντρο υγείας"
  },
  "hu": {
    "language": "Nyelv",
    "active_language": "Aktív nyelv: Magyar",
    "choose_language": "Válasszon nyelvet",
    "dashboard": "Áttekintés",
    "operations": "Műveletek",
    "command_center": "Parancsközpont",
    "navigation": "Navigáció",
    "diagnostics": "Diagnosztika",
    "health_hub": "Állapotközpont"
  },
  "ga": {
    "language": "Teanga",
    "active_language": "Teanga ghníomhach: Gaeilge",
    "choose_language": "Roghnaigh teanga",
    "dashboard": "Forbhreathnú",
    "operations": "Oibríochtaí",
    "command_center": "Lárionad ordaithe",
    "navigation": "Nascleanúint",
    "diagnostics": "Diagnóisic",
    "health_hub": "Mol sláinte"
  },
  "it": {
    "language": "Lingua",
    "active_language": "Lingua attiva: Italiano",
    "choose_language": "Scegli una lingua",
    "dashboard": "Cruscotto",
    "operations": "Operazioni",
    "command_center": "Centro di comando",
    "navigation": "Navigazione",
    "diagnostics": "Diagnostica",
    "health_hub": "Centro stato"
  },
  "lv": {
    "language": "Valoda",
    "active_language": "Aktīvā valoda: Latviešu",
    "choose_language": "Izvēlieties valodu",
    "dashboard": "Pārskats",
    "operations": "Operācijas",
    "command_center": "Komandu centrs",
    "navigation": "Navigācija",
    "diagnostics": "Diagnostika",
    "health_hub": "Stāvokļa centrs"
  },
  "lt": {
    "language": "Kalba",
    "active_language": "Aktyvi kalba: Lietuvių",
    "choose_language": "Pasirinkite kalbą",
    "dashboard": "Apžvalga",
    "operations": "Operacijos",
    "command_center": "Komandų centras",
    "navigation": "Navigacija",
    "diagnostics": "Diagnostika",
    "health_hub": "Būsenos centras"
  },
  "mt": {
    "language": "Lingwa",
    "active_language": "Lingwa attiva: Malti",
    "choose_language": "Agħżel lingwa",
    "dashboard": "Dashboard",
    "operations": "Operazzjonijiet",
    "command_center": "Ċentru tal-kmand",
    "navigation": "Navigazzjoni",
    "diagnostics": "Dijanjostika",
    "health_hub": "Ċentru tas-saħħa"
  },
  "pl": {
    "language": "Język",
    "active_language": "Aktywny język: Polski",
    "choose_language": "Wybierz język",
    "dashboard": "Panel",
    "operations": "Operacje",
    "command_center": "Centrum dowodzenia",
    "navigation": "Nawigacja",
    "diagnostics": "Diagnostyka",
    "health_hub": "Centrum stanu"
  },
  "pt": {
    "language": "Idioma",
    "active_language": "Idioma ativo: Português",
    "choose_language": "Escolha um idioma",
    "dashboard": "Painel",
    "operations": "Operações",
    "command_center": "Centro de comando",
    "navigation": "Navegação",
    "diagnostics": "Diagnóstico",
    "health_hub": "Centro de estado"
  },
  "ro": {
    "language": "Limbă",
    "active_language": "Limba activă: Română",
    "choose_language": "Alegeți o limbă",
    "dashboard": "Panou",
    "operations": "Operațiuni",
    "command_center": "Centru de comandă",
    "navigation": "Navigare",
    "diagnostics": "Diagnosticare",
    "health_hub": "Centru de stare"
  },
  "sk": {
    "language": "Jazyk",
    "active_language": "Aktívny jazyk: Slovenčina",
    "choose_language": "Vyberte jazyk",
    "dashboard": "Prehľad",
    "operations": "Operácie",
    "command_center": "Riadiace centrum",
    "navigation": "Navigácia",
    "diagnostics": "Diagnostika",
    "health_hub": "Centrum stavu"
  },
  "sl": {
    "language": "Jezik",
    "active_language": "Aktivni jezik: Slovenščina",
    "choose_language": "Izberite jezik",
    "dashboard": "Pregled",
    "operations": "Operacije",
    "command_center": "Nadzorni center",
    "navigation": "Navigacija",
    "diagnostics": "Diagnostika",
    "health_hub": "Središče stanja"
  },
  "es": {
    "language": "Idioma",
    "active_language": "Idioma activo: Español",
    "choose_language": "Elegir un idioma",
    "dashboard": "Panel",
    "operations": "Operaciones",
    "command_center": "Centro de comando",
    "navigation": "Navegación",
    "diagnostics": "Diagnóstico",
    "health_hub": "Centro de estado"
  },
  "sv": {
    "language": "Språk",
    "active_language": "Aktivt språk: Svenska",
    "choose_language": "Välj språk",
    "dashboard": "Översikt",
    "operations": "Operationer",
    "command_center": "Kommandocentral",
    "navigation": "Navigering",
    "diagnostics": "Diagnostik",
    "health_hub": "Hälsonav"
  },
  "uk": {
    "language": "Мова",
    "active_language": "Активна мова: Українська",
    "choose_language": "Виберіть мову",
    "dashboard": "Панель",
    "operations": "Операції",
    "command_center": "Командний центр",
    "navigation": "Навігація",
    "diagnostics": "Діагностика",
    "health_hub": "Центр стану"
  }
}
JSON, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }
}


if (!function_exists('p7ops_native_page_slugs')) {
    function p7ops_native_page_slugs(): array
    {
        static $data = null;
        if ($data === null) {
            $decoded = json_decode(<<<'JSON'
{
  "dashboard": {
    "canonical": "/opus-lstsar-manager",
    "aliases": [
      "/opus-lstsar-manager"
    ],
    "slugs": {
      "bg": "табло",
      "hr": "nadzorna-ploča",
      "cs": "přehled",
      "da": "dashboard",
      "nl": "dashboard",
      "en": "dashboard",
      "et": "ülevaade",
      "fi": "koontinäkymä",
      "fr": "tableau-de-bord",
      "de": "übersicht",
      "el": "επισκόπηση",
      "hu": "áttekintés",
      "ga": "forbhreathnú",
      "it": "cruscotto",
      "lv": "pārskats",
      "lt": "apžvalga",
      "mt": "dashboard",
      "pl": "przegląd",
      "pt": "visão-geral",
      "ro": "prezentare-generală",
      "sk": "prehľad",
      "sl": "pregled",
      "es": "panel",
      "sv": "översikt",
      "uk": "огляд"
    }
  },
  "operations": {
    "canonical": "/opus-lstsar-manager/operations",
    "aliases": [
      "/opus-lstsar-manager/operations"
    ],
    "slugs": {
      "bg": "операции",
      "hr": "operacije",
      "cs": "operace",
      "da": "operationer",
      "nl": "operaties",
      "en": "operations",
      "et": "toimingud",
      "fi": "toiminnot",
      "fr": "opérations",
      "de": "operationen",
      "el": "λειτουργίες",
      "hu": "műveletek",
      "ga": "oibríochtaí",
      "it": "operazioni",
      "lv": "operācijas",
      "lt": "operacijos",
      "mt": "operazzjonijiet",
      "pl": "operacje",
      "pt": "operações",
      "ro": "operațiuni",
      "sk": "operácie",
      "sl": "operacije",
      "es": "operaciones",
      "sv": "åtgärder",
      "uk": "операції"
    }
  },
  "command-center": {
    "canonical": "/opus-lstsar-manager/command-center",
    "aliases": [
      "/opus-lstsar-manager/command",
      "/opus-lstsar-manager/command-center"
    ],
    "slugs": {
      "bg": "команден-център",
      "hr": "zapovjedni-centar",
      "cs": "řídicí-centrum",
      "da": "kommandocenter",
      "nl": "commandocentrum",
      "en": "command-center",
      "et": "juhtimiskeskus",
      "fi": "komentokeskus",
      "fr": "centre-de-commande",
      "de": "befehlszentrale",
      "el": "κέντρο-εντολών",
      "hu": "parancsközpont",
      "ga": "lárionad-ordaithe",
      "it": "centro-comando",
      "lv": "komandu-centrs",
      "lt": "komandų-centras",
      "mt": "ċentru-tal-kmand",
      "pl": "centrum-dowodzenia",
      "pt": "centro-de-comando",
      "ro": "centru-de-comandă",
      "sk": "riadiace-centrum",
      "sl": "nadzorni-center",
      "es": "centro-de-comando",
      "sv": "kommandocentral",
      "uk": "командний-центр"
    }
  },
  "navigation": {
    "canonical": "/opus-lstsar-manager/navigation",
    "aliases": [
      "/opus-lstsar-manager/navigation",
      "/opus-lstsar-manager/navigation-polish"
    ],
    "slugs": {
      "bg": "навигация",
      "hr": "navigacija",
      "cs": "navigace",
      "da": "navigation",
      "nl": "navigatie",
      "en": "navigation",
      "et": "navigeerimine",
      "fi": "navigointi",
      "fr": "navigation",
      "de": "navigation",
      "el": "πλοήγηση",
      "hu": "navigáció",
      "ga": "nascleanúint",
      "it": "navigazione",
      "lv": "navigācija",
      "lt": "navigacija",
      "mt": "navigazzjoni",
      "pl": "nawigacja",
      "pt": "navegação",
      "ro": "navigare",
      "sk": "navigácia",
      "sl": "navigacija",
      "es": "navegación",
      "sv": "navigering",
      "uk": "навігація"
    }
  },
  "diagnostics": {
    "canonical": "/opus-lstsar-manager/diagnostics",
    "aliases": [
      "/opus-lstsar-manager/diagnostics",
      "/opus-lstsar-manager/runtime-diagnostics"
    ],
    "slugs": {
      "bg": "диагностика",
      "hr": "dijagnostika",
      "cs": "diagnostika",
      "da": "diagnostik",
      "nl": "diagnostiek",
      "en": "diagnostics",
      "et": "diagnostika",
      "fi": "diagnostiikka",
      "fr": "diagnostics",
      "de": "diagnose",
      "el": "διαγνωστικά",
      "hu": "diagnosztika",
      "ga": "diagnóisic",
      "it": "diagnostica",
      "lv": "diagnostika",
      "lt": "diagnostika",
      "mt": "dijanjostika",
      "pl": "diagnostyka",
      "pt": "diagnóstico",
      "ro": "diagnosticare",
      "sk": "diagnostika",
      "sl": "diagnostika",
      "es": "diagnóstico",
      "sv": "diagnostik",
      "uk": "діагностика"
    }
  },
  "health": {
    "canonical": "/opus-lstsar-manager/health",
    "aliases": [
      "/opus-lstsar-manager/health",
      "/opus-lstsar-manager/health-hub"
    ],
    "slugs": {
      "bg": "състояние",
      "hr": "zdravlje",
      "cs": "zdraví",
      "da": "sundhed",
      "nl": "gezondheid",
      "en": "health",
      "et": "tervis",
      "fi": "tila",
      "fr": "santé",
      "de": "zustand",
      "el": "υγεία",
      "hu": "állapot",
      "ga": "sláinte",
      "it": "salute",
      "lv": "veselība",
      "lt": "būsena",
      "mt": "saħħa",
      "pl": "stan",
      "pt": "saúde",
      "ro": "sănătate",
      "sk": "zdravie",
      "sl": "zdravje",
      "es": "salud",
      "sv": "hälsa",
      "uk": "стан"
    }
  }
}
JSON, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }
}


if (!function_exists('p7ops_i18n_page_translation_dictionary')) {
    function p7ops_i18n_page_translation_dictionary(): array
    {
        static $data = null;
        if ($data === null) {
            $decoded = json_decode(<<<'JSON'
{
  "bg": {
    "OPUS OPS Dashboard": "Табло OPUS OPS",
    "OPUS OPS Operations Console": "Операционна конзола OPUS OPS",
    "Operations digest": "Обобщение на операциите",
    "Health snapshot": "Моментно състояние",
    "Quick access": "Бърз достъп",
    "Operations detail": "Детайли за операциите",
    "Source summary": "Обобщение на източника",
    "Destination summary": "Обобщение на дестинацията",
    "Runtime diagnostics": "Диагностика по време на изпълнение",
    "Dashboard": "Табло",
    "Operations": "Операции",
    "Command Center": "Команден център",
    "Navigation": "Навигация",
    "Diagnostics": "Диагностика",
    "Health Hub": "Център за състояние",
    "Language": "Език",
    "Langue": "Език",
    "Choose a language": "Изберете език",
    "Choisir une langue": "Изберете език",
    "Status": "Състояние",
    "Statut": "Състояние",
    "Site": "Сайт",
    "Action": "Действие",
    "Preview": "Преглед",
    "Aperçu": "Преглед",
    "Dry run": "Сух тест",
    "Simulation": "Сух тест",
    "Audit": "Одит",
    "Open": "Отвори",
    "Ouvrir": "Отвори",
    "Run": "Изпълни",
    "Exécuter": "Изпълни",
    "Overview": "Преглед",
    "Vue d’ensemble": "Преглед",
    "Summary": "Резюме",
    "Résumé": "Резюме",
    "Details": "Детайли",
    "Détails": "Детайли",
    "Checks": "Проверки",
    "Contrôles": "Проверки",
    "Files": "Файлове",
    "Fichiers": "Файлове",
    "Version": "Версия",
    "Route": "Маршрут",
    "Routes": "Маршрути",
    "Smoke": "Smoke тест",
    "Smokes": "Smoke тестове",
    "Warning": "Предупреждение",
    "Avertissement": "Предупреждение",
    "Error": "Грешка",
    "Erreur": "Грешка",
    "No side effects": "Без странични ефекти",
    "Sans effet de bord": "Без странични ефекти",
    "Synthèse OPS": "OPS резюме",
    "Console détaillée séparée": "Отделна подробна конзола",
    "État global": "Глобално състояние",
    "Navigation directe": "Директна навигация"
  },
  "hr": {
    "OPUS OPS Dashboard": "Nadzorna ploča OPUS OPS",
    "OPUS OPS Operations Console": "Konzola operacija OPUS OPS",
    "Operations digest": "Sažetak operacija",
    "Health snapshot": "Trenutno stanje",
    "Quick access": "Brzi pristup",
    "Operations detail": "Detalji operacija",
    "Source summary": "Sažetak izvora",
    "Destination summary": "Sažetak odredišta",
    "Runtime diagnostics": "Dijagnostika izvođenja",
    "Dashboard": "Nadzorna ploča",
    "Operations": "Operacije",
    "Command Center": "Zapovjedni centar",
    "Navigation": "Navigacija",
    "Diagnostics": "Dijagnostika",
    "Health Hub": "Središte stanja",
    "Language": "Jezik",
    "Langue": "Jezik",
    "Choose a language": "Odaberite jezik",
    "Choisir une langue": "Odaberite jezik",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Web-mjesto",
    "Action": "Radnja",
    "Preview": "Pregled",
    "Aperçu": "Pregled",
    "Dry run": "Suho pokretanje",
    "Simulation": "Suho pokretanje",
    "Audit": "Revizija",
    "Open": "Otvori",
    "Ouvrir": "Otvori",
    "Run": "Pokreni",
    "Exécuter": "Pokreni",
    "Overview": "Pregled",
    "Vue d’ensemble": "Pregled",
    "Summary": "Sažetak",
    "Résumé": "Sažetak",
    "Details": "Detalji",
    "Détails": "Detalji",
    "Checks": "Provjere",
    "Contrôles": "Provjere",
    "Files": "Datoteke",
    "Fichiers": "Datoteke",
    "Version": "Verzija",
    "Route": "Ruta",
    "Routes": "Rute",
    "Smoke": "Smoke test",
    "Smokes": "Smoke testovi",
    "Warning": "Upozorenje",
    "Avertissement": "Upozorenje",
    "Error": "Greška",
    "Erreur": "Greška",
    "No side effects": "Bez nuspojava",
    "Sans effet de bord": "Bez nuspojava",
    "Synthèse OPS": "OPS sažetak",
    "Console détaillée séparée": "Odvojena detaljna konzola",
    "État global": "Globalno stanje",
    "Navigation directe": "Izravna navigacija"
  },
  "cs": {
    "OPUS OPS Dashboard": "Přehled OPUS OPS",
    "OPUS OPS Operations Console": "Konzole operací OPUS OPS",
    "Operations digest": "Souhrn operací",
    "Health snapshot": "Snímek stavu",
    "Quick access": "Rychlý přístup",
    "Operations detail": "Detail operací",
    "Source summary": "Souhrn zdroje",
    "Destination summary": "Souhrn cíle",
    "Runtime diagnostics": "Diagnostika běhu",
    "Dashboard": "Přehled",
    "Operations": "Operace",
    "Command Center": "Řídicí centrum",
    "Navigation": "Navigace",
    "Diagnostics": "Diagnostika",
    "Health Hub": "Centrum stavu",
    "Language": "Jazyk",
    "Langue": "Jazyk",
    "Choose a language": "Vyberte jazyk",
    "Choisir une langue": "Vyberte jazyk",
    "Status": "Stav",
    "Statut": "Stav",
    "Site": "Web",
    "Action": "Akce",
    "Preview": "Náhled",
    "Aperçu": "Náhled",
    "Dry run": "Suchý běh",
    "Simulation": "Suchý běh",
    "Audit": "Audit",
    "Open": "Otevřít",
    "Ouvrir": "Otevřít",
    "Run": "Spustit",
    "Exécuter": "Spustit",
    "Overview": "Přehled",
    "Vue d’ensemble": "Přehled",
    "Summary": "Souhrn",
    "Résumé": "Souhrn",
    "Details": "Detaily",
    "Détails": "Detaily",
    "Checks": "Kontroly",
    "Contrôles": "Kontroly",
    "Files": "Soubory",
    "Fichiers": "Soubory",
    "Version": "Verze",
    "Route": "Trasa",
    "Routes": "Trasy",
    "Smoke": "Smoke test",
    "Smokes": "Smoke testy",
    "Warning": "Varování",
    "Avertissement": "Varování",
    "Error": "Chyba",
    "Erreur": "Chyba",
    "No side effects": "Bez vedlejších účinků",
    "Sans effet de bord": "Bez vedlejších účinků",
    "Synthèse OPS": "Souhrn OPS",
    "Console détaillée séparée": "Samostatná detailní konzole",
    "État global": "Celkový stav",
    "Navigation directe": "Přímá navigace"
  },
  "da": {
    "OPUS OPS Dashboard": "Dashboard OPUS OPS",
    "OPUS OPS Operations Console": "Operationskonsol OPUS OPS",
    "Operations digest": "Operationsoversigt",
    "Health snapshot": "Sundhedsstatus",
    "Quick access": "Hurtig adgang",
    "Operations detail": "Operationsdetaljer",
    "Source summary": "Kildeoversigt",
    "Destination summary": "Destinationsoversigt",
    "Runtime diagnostics": "Kørselsdiagnostik",
    "Dashboard": "Dashboard",
    "Operations": "Operationer",
    "Command Center": "Kommandocenter",
    "Navigation": "Navigation",
    "Diagnostics": "Diagnostik",
    "Health Hub": "Sundhedshub",
    "Language": "Sprog",
    "Langue": "Sprog",
    "Choose a language": "Vælg et sprog",
    "Choisir une langue": "Vælg et sprog",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Websted",
    "Action": "Handling",
    "Preview": "Forhåndsvisning",
    "Aperçu": "Forhåndsvisning",
    "Dry run": "Tørkørsel",
    "Simulation": "Tørkørsel",
    "Audit": "Audit",
    "Open": "Åbn",
    "Ouvrir": "Åbn",
    "Run": "Kør",
    "Exécuter": "Kør",
    "Overview": "Oversigt",
    "Vue d’ensemble": "Oversigt",
    "Summary": "Resumé",
    "Résumé": "Resumé",
    "Details": "Detaljer",
    "Détails": "Detaljer",
    "Checks": "Kontroller",
    "Contrôles": "Kontroller",
    "Files": "Filer",
    "Fichiers": "Filer",
    "Version": "Version",
    "Route": "Rute",
    "Routes": "Ruter",
    "Smoke": "Smoke-test",
    "Smokes": "Smoke-tests",
    "Warning": "Advarsel",
    "Avertissement": "Advarsel",
    "Error": "Fejl",
    "Erreur": "Fejl",
    "No side effects": "Ingen bivirkninger",
    "Sans effet de bord": "Ingen bivirkninger",
    "Synthèse OPS": "OPS-oversigt",
    "Console détaillée séparée": "Separat detaljeret konsol",
    "État global": "Global status",
    "Navigation directe": "Direkte navigation"
  },
  "nl": {
    "OPUS OPS Dashboard": "Dashboard OPUS OPS",
    "OPUS OPS Operations Console": "Operatieconsole OPUS OPS",
    "Operations digest": "Operatieoverzicht",
    "Health snapshot": "Gezondheidssnapshot",
    "Quick access": "Snelle toegang",
    "Operations detail": "Operatiedetail",
    "Source summary": "Bronoverzicht",
    "Destination summary": "Doeloverzicht",
    "Runtime diagnostics": "Runtime-diagnostiek",
    "Dashboard": "Dashboard",
    "Operations": "Operaties",
    "Command Center": "Commandocentrum",
    "Navigation": "Navigatie",
    "Diagnostics": "Diagnostiek",
    "Health Hub": "Gezondheidscentrum",
    "Language": "Taal",
    "Langue": "Taal",
    "Choose a language": "Kies een taal",
    "Choisir une langue": "Kies een taal",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Site",
    "Action": "Actie",
    "Preview": "Voorbeeld",
    "Aperçu": "Voorbeeld",
    "Dry run": "Droge run",
    "Simulation": "Droge run",
    "Audit": "Audit",
    "Open": "Openen",
    "Ouvrir": "Openen",
    "Run": "Uitvoeren",
    "Exécuter": "Uitvoeren",
    "Overview": "Overzicht",
    "Vue d’ensemble": "Overzicht",
    "Summary": "Samenvatting",
    "Résumé": "Samenvatting",
    "Details": "Details",
    "Détails": "Details",
    "Checks": "Controles",
    "Contrôles": "Controles",
    "Files": "Bestanden",
    "Fichiers": "Bestanden",
    "Version": "Versie",
    "Route": "Route",
    "Routes": "Routes",
    "Smoke": "Smoke-test",
    "Smokes": "Smoke-tests",
    "Warning": "Waarschuwing",
    "Avertissement": "Waarschuwing",
    "Error": "Fout",
    "Erreur": "Fout",
    "No side effects": "Geen neveneffecten",
    "Sans effet de bord": "Geen neveneffecten",
    "Synthèse OPS": "OPS-samenvatting",
    "Console détaillée séparée": "Aparte detailconsole",
    "État global": "Globale status",
    "Navigation directe": "Directe navigatie"
  },
  "en": {
    "OPUS OPS Dashboard": "OPUS OPS Dashboard",
    "OPUS OPS Operations Console": "OPUS OPS Operations Console",
    "Operations digest": "Operations digest",
    "Health snapshot": "Health snapshot",
    "Quick access": "Quick access",
    "Operations detail": "Operations detail",
    "Source summary": "Source summary",
    "Destination summary": "Destination summary",
    "Runtime diagnostics": "Runtime diagnostics",
    "Dashboard": "Dashboard",
    "Operations": "Operations",
    "Command Center": "Command Center",
    "Navigation": "Navigation",
    "Diagnostics": "Diagnostics",
    "Health Hub": "Health Hub",
    "Language": "Language",
    "Langue": "Language",
    "Choose a language": "Choose a language",
    "Choisir une langue": "Choose a language",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Site",
    "Action": "Action",
    "Preview": "Preview",
    "Aperçu": "Preview",
    "Dry run": "Dry run",
    "Simulation": "Dry run",
    "Audit": "Audit",
    "Open": "Open",
    "Ouvrir": "Open",
    "Run": "Run",
    "Exécuter": "Run",
    "Overview": "Overview",
    "Vue d’ensemble": "Overview",
    "Summary": "Summary",
    "Résumé": "Summary",
    "Details": "Details",
    "Détails": "Details",
    "Checks": "Checks",
    "Contrôles": "Checks",
    "Files": "Files",
    "Fichiers": "Files",
    "Version": "Version",
    "Route": "Route",
    "Routes": "Routes",
    "Smoke": "Smoke",
    "Smokes": "Smokes",
    "Warning": "Warning",
    "Avertissement": "Warning",
    "Error": "Error",
    "Erreur": "Error",
    "No side effects": "No side effects",
    "Sans effet de bord": "No side effects",
    "Synthèse OPS": "OPS summary",
    "Console détaillée séparée": "Separate detailed console",
    "État global": "Global status",
    "Navigation directe": "Direct navigation"
  },
  "et": {
    "OPUS OPS Dashboard": "Ülevaade OPUS OPS",
    "OPUS OPS Operations Console": "Toimingute konsool OPUS OPS",
    "Operations digest": "Toimingute kokkuvõte",
    "Health snapshot": "Tervise hetkevaade",
    "Quick access": "Kiirjuurdepääs",
    "Operations detail": "Toimingute detailid",
    "Source summary": "Allika kokkuvõte",
    "Destination summary": "Sihtkoha kokkuvõte",
    "Runtime diagnostics": "Käitusaegne diagnostika",
    "Dashboard": "Ülevaade",
    "Operations": "Toimingud",
    "Command Center": "Juhtimiskeskus",
    "Navigation": "Navigeerimine",
    "Diagnostics": "Diagnostika",
    "Health Hub": "Tervisekeskus",
    "Language": "Keel",
    "Langue": "Keel",
    "Choose a language": "Valige keel",
    "Choisir une langue": "Valige keel",
    "Status": "Olek",
    "Statut": "Olek",
    "Site": "Sait",
    "Action": "Tegevus",
    "Preview": "Eelvaade",
    "Aperçu": "Eelvaade",
    "Dry run": "Kuivkäivitus",
    "Simulation": "Kuivkäivitus",
    "Audit": "Audit",
    "Open": "Ava",
    "Ouvrir": "Ava",
    "Run": "Käivita",
    "Exécuter": "Käivita",
    "Overview": "Ülevaade",
    "Vue d’ensemble": "Ülevaade",
    "Summary": "Kokkuvõte",
    "Résumé": "Kokkuvõte",
    "Details": "Detailid",
    "Détails": "Detailid",
    "Checks": "Kontrollid",
    "Contrôles": "Kontrollid",
    "Files": "Failid",
    "Fichiers": "Failid",
    "Version": "Versioon",
    "Route": "Marsruut",
    "Routes": "Marsruudid",
    "Smoke": "Smoke-test",
    "Smokes": "Smoke-testid",
    "Warning": "Hoiatus",
    "Avertissement": "Hoiatus",
    "Error": "Viga",
    "Erreur": "Viga",
    "No side effects": "Kõrvalmõjudeta",
    "Sans effet de bord": "Kõrvalmõjudeta",
    "Synthèse OPS": "OPS kokkuvõte",
    "Console détaillée séparée": "Eraldi detailne konsool",
    "État global": "Üldine olek",
    "Navigation directe": "Otsenavigeerimine"
  },
  "fi": {
    "OPUS OPS Dashboard": "Koontinäkymä OPUS OPS",
    "OPUS OPS Operations Console": "Toimintokonsoli OPUS OPS",
    "Operations digest": "Toimintojen yhteenveto",
    "Health snapshot": "Tilan tilannekuva",
    "Quick access": "Pikakäyttö",
    "Operations detail": "Toimintojen tiedot",
    "Source summary": "Lähteen yhteenveto",
    "Destination summary": "Kohteen yhteenveto",
    "Runtime diagnostics": "Ajonaikainen diagnostiikka",
    "Dashboard": "Koontinäkymä",
    "Operations": "Toiminnot",
    "Command Center": "Komentokeskus",
    "Navigation": "Navigointi",
    "Diagnostics": "Diagnostiikka",
    "Health Hub": "Tilakeskus",
    "Language": "Kieli",
    "Langue": "Kieli",
    "Choose a language": "Valitse kieli",
    "Choisir une langue": "Valitse kieli",
    "Status": "Tila",
    "Statut": "Tila",
    "Site": "Sivusto",
    "Action": "Toiminto",
    "Preview": "Esikatselu",
    "Aperçu": "Esikatselu",
    "Dry run": "Kuiva ajo",
    "Simulation": "Kuiva ajo",
    "Audit": "Auditointi",
    "Open": "Avaa",
    "Ouvrir": "Avaa",
    "Run": "Suorita",
    "Exécuter": "Suorita",
    "Overview": "Yleiskatsaus",
    "Vue d’ensemble": "Yleiskatsaus",
    "Summary": "Yhteenveto",
    "Résumé": "Yhteenveto",
    "Details": "Tiedot",
    "Détails": "Tiedot",
    "Checks": "Tarkistukset",
    "Contrôles": "Tarkistukset",
    "Files": "Tiedostot",
    "Fichiers": "Tiedostot",
    "Version": "Versio",
    "Route": "Reitti",
    "Routes": "Reitit",
    "Smoke": "Smoke-testi",
    "Smokes": "Smoke-testit",
    "Warning": "Varoitus",
    "Avertissement": "Varoitus",
    "Error": "Virhe",
    "Erreur": "Virhe",
    "No side effects": "Ei sivuvaikutuksia",
    "Sans effet de bord": "Ei sivuvaikutuksia",
    "Synthèse OPS": "OPS-yhteenveto",
    "Console détaillée séparée": "Erillinen yksityiskohtainen konsoli",
    "État global": "Yleistila",
    "Navigation directe": "Suora navigointi"
  },
  "fr": {
    "OPUS OPS Dashboard": "Tableau de bord OPUS OPS",
    "OPUS OPS Operations Console": "Console d’opérations OPUS OPS",
    "Operations digest": "Synthèse des opérations",
    "Health snapshot": "État de santé",
    "Quick access": "Accès rapide",
    "Operations detail": "Détail des opérations",
    "Source summary": "Résumé source",
    "Destination summary": "Résumé destination",
    "Runtime diagnostics": "Diagnostics d’exécution",
    "Dashboard": "Tableau de bord",
    "Operations": "Opérations",
    "Command Center": "Centre de commande",
    "Navigation": "Navigation",
    "Diagnostics": "Diagnostics",
    "Health Hub": "Centre de santé",
    "Language": "Langue",
    "Langue": "Langue",
    "Choose a language": "Choisir une langue",
    "Choisir une langue": "Choisir une langue",
    "Status": "Statut",
    "Statut": "Statut",
    "Site": "Site",
    "Action": "Action",
    "Preview": "Aperçu",
    "Aperçu": "Aperçu",
    "Dry run": "Simulation",
    "Simulation": "Simulation",
    "Audit": "Audit",
    "Open": "Ouvrir",
    "Ouvrir": "Ouvrir",
    "Run": "Exécuter",
    "Exécuter": "Exécuter",
    "Overview": "Vue d’ensemble",
    "Vue d’ensemble": "Vue d’ensemble",
    "Summary": "Résumé",
    "Résumé": "Résumé",
    "Details": "Détails",
    "Détails": "Détails",
    "Checks": "Contrôles",
    "Contrôles": "Contrôles",
    "Files": "Fichiers",
    "Fichiers": "Fichiers",
    "Version": "Version",
    "Route": "Route",
    "Routes": "Routes",
    "Smoke": "Smoke test",
    "Smokes": "Smoke tests",
    "Warning": "Avertissement",
    "Avertissement": "Avertissement",
    "Error": "Erreur",
    "Erreur": "Erreur",
    "No side effects": "Sans effet de bord",
    "Sans effet de bord": "Sans effet de bord",
    "Synthèse OPS": "Synthèse OPS",
    "Console détaillée séparée": "Console détaillée séparée",
    "État global": "État global",
    "Navigation directe": "Navigation directe"
  },
  "de": {
    "OPUS OPS Dashboard": "Übersicht OPUS OPS",
    "OPUS OPS Operations Console": "Betriebskonsole OPUS OPS",
    "Operations digest": "Operationsübersicht",
    "Health snapshot": "Statusmomentaufnahme",
    "Quick access": "Schnellzugriff",
    "Operations detail": "Operationsdetails",
    "Source summary": "Quellenübersicht",
    "Destination summary": "Zielübersicht",
    "Runtime diagnostics": "Laufzeitdiagnose",
    "Dashboard": "Übersicht",
    "Operations": "Operationen",
    "Command Center": "Befehlszentrale",
    "Navigation": "Navigation",
    "Diagnostics": "Diagnose",
    "Health Hub": "Statuszentrale",
    "Language": "Sprache",
    "Langue": "Sprache",
    "Choose a language": "Sprache auswählen",
    "Choisir une langue": "Sprache auswählen",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Site",
    "Action": "Aktion",
    "Preview": "Vorschau",
    "Aperçu": "Vorschau",
    "Dry run": "Testlauf",
    "Simulation": "Testlauf",
    "Audit": "Audit",
    "Open": "Öffnen",
    "Ouvrir": "Öffnen",
    "Run": "Ausführen",
    "Exécuter": "Ausführen",
    "Overview": "Überblick",
    "Vue d’ensemble": "Überblick",
    "Summary": "Zusammenfassung",
    "Résumé": "Zusammenfassung",
    "Details": "Details",
    "Détails": "Details",
    "Checks": "Prüfungen",
    "Contrôles": "Prüfungen",
    "Files": "Dateien",
    "Fichiers": "Dateien",
    "Version": "Version",
    "Route": "Route",
    "Routes": "Routen",
    "Smoke": "Smoke-Test",
    "Smokes": "Smoke-Tests",
    "Warning": "Warnung",
    "Avertissement": "Warnung",
    "Error": "Fehler",
    "Erreur": "Fehler",
    "No side effects": "Ohne Nebenwirkungen",
    "Sans effet de bord": "Ohne Nebenwirkungen",
    "Synthèse OPS": "OPS-Zusammenfassung",
    "Console détaillée séparée": "Separate Detailkonsole",
    "État global": "Gesamtstatus",
    "Navigation directe": "Direkte Navigation"
  },
  "el": {
    "OPUS OPS Dashboard": "Πίνακας ελέγχου OPUS OPS",
    "OPUS OPS Operations Console": "Κονσόλα λειτουργιών OPUS OPS",
    "Operations digest": "Σύνοψη λειτουργιών",
    "Health snapshot": "Στιγμιότυπο υγείας",
    "Quick access": "Γρήγορη πρόσβαση",
    "Operations detail": "Λεπτομέρειες λειτουργιών",
    "Source summary": "Σύνοψη πηγής",
    "Destination summary": "Σύνοψη προορισμού",
    "Runtime diagnostics": "Διαγνωστικά εκτέλεσης",
    "Dashboard": "Πίνακας ελέγχου",
    "Operations": "Λειτουργίες",
    "Command Center": "Κέντρο εντολών",
    "Navigation": "Πλοήγηση",
    "Diagnostics": "Διαγνωστικά",
    "Health Hub": "Κέντρο υγείας",
    "Language": "Γλώσσα",
    "Langue": "Γλώσσα",
    "Choose a language": "Επιλέξτε γλώσσα",
    "Choisir une langue": "Επιλέξτε γλώσσα",
    "Status": "Κατάσταση",
    "Statut": "Κατάσταση",
    "Site": "Ιστότοπος",
    "Action": "Ενέργεια",
    "Preview": "Προεπισκόπηση",
    "Aperçu": "Προεπισκόπηση",
    "Dry run": "Δοκιμαστική εκτέλεση",
    "Simulation": "Δοκιμαστική εκτέλεση",
    "Audit": "Έλεγχος",
    "Open": "Άνοιγμα",
    "Ouvrir": "Άνοιγμα",
    "Run": "Εκτέλεση",
    "Exécuter": "Εκτέλεση",
    "Overview": "Επισκόπηση",
    "Vue d’ensemble": "Επισκόπηση",
    "Summary": "Σύνοψη",
    "Résumé": "Σύνοψη",
    "Details": "Λεπτομέρειες",
    "Détails": "Λεπτομέρειες",
    "Checks": "Έλεγχοι",
    "Contrôles": "Έλεγχοι",
    "Files": "Αρχεία",
    "Fichiers": "Αρχεία",
    "Version": "Έκδοση",
    "Route": "Διαδρομή",
    "Routes": "Διαδρομές",
    "Smoke": "Smoke test",
    "Smokes": "Smoke tests",
    "Warning": "Προειδοποίηση",
    "Avertissement": "Προειδοποίηση",
    "Error": "Σφάλμα",
    "Erreur": "Σφάλμα",
    "No side effects": "Χωρίς παρενέργειες",
    "Sans effet de bord": "Χωρίς παρενέργειες",
    "Synthèse OPS": "Σύνοψη OPS",
    "Console détaillée séparée": "Ξεχωριστή αναλυτική κονσόλα",
    "État global": "Συνολική κατάσταση",
    "Navigation directe": "Άμεση πλοήγηση"
  },
  "hu": {
    "OPUS OPS Dashboard": "Áttekintés OPUS OPS",
    "OPUS OPS Operations Console": "Műveleti konzol OPUS OPS",
    "Operations digest": "Műveleti összefoglaló",
    "Health snapshot": "Állapotpillanatkép",
    "Quick access": "Gyors hozzáférés",
    "Operations detail": "Műveleti részletek",
    "Source summary": "Forrás összefoglaló",
    "Destination summary": "Cél összefoglaló",
    "Runtime diagnostics": "Futásidejű diagnosztika",
    "Dashboard": "Áttekintés",
    "Operations": "Műveletek",
    "Command Center": "Parancsközpont",
    "Navigation": "Navigáció",
    "Diagnostics": "Diagnosztika",
    "Health Hub": "Állapotközpont",
    "Language": "Nyelv",
    "Langue": "Nyelv",
    "Choose a language": "Válasszon nyelvet",
    "Choisir une langue": "Válasszon nyelvet",
    "Status": "Állapot",
    "Statut": "Állapot",
    "Site": "Webhely",
    "Action": "Művelet",
    "Preview": "Előnézet",
    "Aperçu": "Előnézet",
    "Dry run": "Próbafuttatás",
    "Simulation": "Próbafuttatás",
    "Audit": "Audit",
    "Open": "Megnyitás",
    "Ouvrir": "Megnyitás",
    "Run": "Futtatás",
    "Exécuter": "Futtatás",
    "Overview": "Áttekintés",
    "Vue d’ensemble": "Áttekintés",
    "Summary": "Összefoglaló",
    "Résumé": "Összefoglaló",
    "Details": "Részletek",
    "Détails": "Részletek",
    "Checks": "Ellenőrzések",
    "Contrôles": "Ellenőrzések",
    "Files": "Fájlok",
    "Fichiers": "Fájlok",
    "Version": "Verzió",
    "Route": "Útvonal",
    "Routes": "Útvonalak",
    "Smoke": "Smoke teszt",
    "Smokes": "Smoke tesztek",
    "Warning": "Figyelmeztetés",
    "Avertissement": "Figyelmeztetés",
    "Error": "Hiba",
    "Erreur": "Hiba",
    "No side effects": "Mellékhatások nélkül",
    "Sans effet de bord": "Mellékhatások nélkül",
    "Synthèse OPS": "OPS összefoglaló",
    "Console détaillée séparée": "Külön részletes konzol",
    "État global": "Általános állapot",
    "Navigation directe": "Közvetlen navigáció"
  },
  "ga": {
    "OPUS OPS Dashboard": "Forbhreathnú OPUS OPS",
    "OPUS OPS Operations Console": "Consól oibríochtaí OPUS OPS",
    "Operations digest": "Achoimre oibríochtaí",
    "Health snapshot": "Léargas sláinte",
    "Quick access": "Rochtain thapa",
    "Operations detail": "Sonraí oibríochtaí",
    "Source summary": "Achoimre foinse",
    "Destination summary": "Achoimre cinn scríbe",
    "Runtime diagnostics": "Diagnóisic rith-ama",
    "Dashboard": "Forbhreathnú",
    "Operations": "Oibríochtaí",
    "Command Center": "Lárionad ordaithe",
    "Navigation": "Nascleanúint",
    "Diagnostics": "Diagnóisic",
    "Health Hub": "Mol sláinte",
    "Language": "Teanga",
    "Langue": "Teanga",
    "Choose a language": "Roghnaigh teanga",
    "Choisir une langue": "Roghnaigh teanga",
    "Status": "Stádas",
    "Statut": "Stádas",
    "Site": "Suíomh",
    "Action": "Gníomh",
    "Preview": "Réamhamharc",
    "Aperçu": "Réamhamharc",
    "Dry run": "Rith thirim",
    "Simulation": "Rith thirim",
    "Audit": "Iniúchadh",
    "Open": "Oscail",
    "Ouvrir": "Oscail",
    "Run": "Rith",
    "Exécuter": "Rith",
    "Overview": "Forbhreathnú",
    "Vue d’ensemble": "Forbhreathnú",
    "Summary": "Achoimre",
    "Résumé": "Achoimre",
    "Details": "Sonraí",
    "Détails": "Sonraí",
    "Checks": "Seiceálacha",
    "Contrôles": "Seiceálacha",
    "Files": "Comhaid",
    "Fichiers": "Comhaid",
    "Version": "Leagan",
    "Route": "Bealach",
    "Routes": "Bealaí",
    "Smoke": "Smoke test",
    "Smokes": "Smoke tests",
    "Warning": "Rabhadh",
    "Avertissement": "Rabhadh",
    "Error": "Earráid",
    "Erreur": "Earráid",
    "No side effects": "Gan fo-éifeachtaí",
    "Sans effet de bord": "Gan fo-éifeachtaí",
    "Synthèse OPS": "Achoimre OPS",
    "Console détaillée séparée": "Consól mionsonraithe ar leith",
    "État global": "Stádas domhanda",
    "Navigation directe": "Nascleanúint dhíreach"
  },
  "it": {
    "OPUS OPS Dashboard": "Cruscotto OPUS OPS",
    "OPUS OPS Operations Console": "Console operazioni OPUS OPS",
    "Operations digest": "Sintesi operazioni",
    "Health snapshot": "Stato salute",
    "Quick access": "Accesso rapido",
    "Operations detail": "Dettaglio operazioni",
    "Source summary": "Riepilogo sorgente",
    "Destination summary": "Riepilogo destinazione",
    "Runtime diagnostics": "Diagnostica runtime",
    "Dashboard": "Cruscotto",
    "Operations": "Operazioni",
    "Command Center": "Centro di comando",
    "Navigation": "Navigazione",
    "Diagnostics": "Diagnostica",
    "Health Hub": "Centro stato",
    "Language": "Lingua",
    "Langue": "Lingua",
    "Choose a language": "Scegli una lingua",
    "Choisir une langue": "Scegli una lingua",
    "Status": "Stato",
    "Statut": "Stato",
    "Site": "Sito",
    "Action": "Azione",
    "Preview": "Anteprima",
    "Aperçu": "Anteprima",
    "Dry run": "Simulazione",
    "Simulation": "Simulazione",
    "Audit": "Audit",
    "Open": "Apri",
    "Ouvrir": "Apri",
    "Run": "Esegui",
    "Exécuter": "Esegui",
    "Overview": "Panoramica",
    "Vue d’ensemble": "Panoramica",
    "Summary": "Riepilogo",
    "Résumé": "Riepilogo",
    "Details": "Dettagli",
    "Détails": "Dettagli",
    "Checks": "Controlli",
    "Contrôles": "Controlli",
    "Files": "File",
    "Fichiers": "File",
    "Version": "Versione",
    "Route": "Rotta",
    "Routes": "Rotte",
    "Smoke": "Smoke test",
    "Smokes": "Smoke test",
    "Warning": "Avviso",
    "Avertissement": "Avviso",
    "Error": "Errore",
    "Erreur": "Errore",
    "No side effects": "Senza effetti collaterali",
    "Sans effet de bord": "Senza effetti collaterali",
    "Synthèse OPS": "Sintesi OPS",
    "Console détaillée séparée": "Console dettagliata separata",
    "État global": "Stato globale",
    "Navigation directe": "Navigazione diretta"
  },
  "lv": {
    "OPUS OPS Dashboard": "Pārskats OPUS OPS",
    "OPUS OPS Operations Console": "Operāciju konsole OPUS OPS",
    "Operations digest": "Operāciju kopsavilkums",
    "Health snapshot": "Stāvokļa momentuzņēmums",
    "Quick access": "Ātrā piekļuve",
    "Operations detail": "Operāciju detaļas",
    "Source summary": "Avota kopsavilkums",
    "Destination summary": "Galamērķa kopsavilkums",
    "Runtime diagnostics": "Izpildlaika diagnostika",
    "Dashboard": "Pārskats",
    "Operations": "Operācijas",
    "Command Center": "Komandu centrs",
    "Navigation": "Navigācija",
    "Diagnostics": "Diagnostika",
    "Health Hub": "Stāvokļa centrs",
    "Language": "Valoda",
    "Langue": "Valoda",
    "Choose a language": "Izvēlieties valodu",
    "Choisir une langue": "Izvēlieties valodu",
    "Status": "Statuss",
    "Statut": "Statuss",
    "Site": "Vietne",
    "Action": "Darbība",
    "Preview": "Priekšskatījums",
    "Aperçu": "Priekšskatījums",
    "Dry run": "Sausais tests",
    "Simulation": "Sausais tests",
    "Audit": "Audits",
    "Open": "Atvērt",
    "Ouvrir": "Atvērt",
    "Run": "Palaist",
    "Exécuter": "Palaist",
    "Overview": "Pārskats",
    "Vue d’ensemble": "Pārskats",
    "Summary": "Kopsavilkums",
    "Résumé": "Kopsavilkums",
    "Details": "Detaļas",
    "Détails": "Detaļas",
    "Checks": "Pārbaudes",
    "Contrôles": "Pārbaudes",
    "Files": "Faili",
    "Fichiers": "Faili",
    "Version": "Versija",
    "Route": "Maršruts",
    "Routes": "Maršruti",
    "Smoke": "Smoke tests",
    "Smokes": "Smoke testi",
    "Warning": "Brīdinājums",
    "Avertissement": "Brīdinājums",
    "Error": "Kļūda",
    "Erreur": "Kļūda",
    "No side effects": "Bez blakus efektiem",
    "Sans effet de bord": "Bez blakus efektiem",
    "Synthèse OPS": "OPS kopsavilkums",
    "Console détaillée séparée": "Atsevišķa detalizēta konsole",
    "État global": "Globālais statuss",
    "Navigation directe": "Tiešā navigācija"
  },
  "lt": {
    "OPUS OPS Dashboard": "Apžvalga OPUS OPS",
    "OPUS OPS Operations Console": "Operacijų konsolė OPUS OPS",
    "Operations digest": "Operacijų suvestinė",
    "Health snapshot": "Būsenos momentinė kopija",
    "Quick access": "Greita prieiga",
    "Operations detail": "Operacijų detalės",
    "Source summary": "Šaltinio suvestinė",
    "Destination summary": "Paskirties suvestinė",
    "Runtime diagnostics": "Vykdymo diagnostika",
    "Dashboard": "Apžvalga",
    "Operations": "Operacijos",
    "Command Center": "Komandų centras",
    "Navigation": "Navigacija",
    "Diagnostics": "Diagnostika",
    "Health Hub": "Būsenos centras",
    "Language": "Kalba",
    "Langue": "Kalba",
    "Choose a language": "Pasirinkite kalbą",
    "Choisir une langue": "Pasirinkite kalbą",
    "Status": "Būsena",
    "Statut": "Būsena",
    "Site": "Svetainė",
    "Action": "Veiksmas",
    "Preview": "Peržiūra",
    "Aperçu": "Peržiūra",
    "Dry run": "Bandomasis paleidimas",
    "Simulation": "Bandomasis paleidimas",
    "Audit": "Auditas",
    "Open": "Atidaryti",
    "Ouvrir": "Atidaryti",
    "Run": "Vykdyti",
    "Exécuter": "Vykdyti",
    "Overview": "Apžvalga",
    "Vue d’ensemble": "Apžvalga",
    "Summary": "Suvestinė",
    "Résumé": "Suvestinė",
    "Details": "Detalės",
    "Détails": "Detalės",
    "Checks": "Patikros",
    "Contrôles": "Patikros",
    "Files": "Failai",
    "Fichiers": "Failai",
    "Version": "Versija",
    "Route": "Maršrutas",
    "Routes": "Maršrutai",
    "Smoke": "Smoke testas",
    "Smokes": "Smoke testai",
    "Warning": "Įspėjimas",
    "Avertissement": "Įspėjimas",
    "Error": "Klaida",
    "Erreur": "Klaida",
    "No side effects": "Be šalutinių efektų",
    "Sans effet de bord": "Be šalutinių efektų",
    "Synthèse OPS": "OPS suvestinė",
    "Console détaillée séparée": "Atskira detali konsolė",
    "État global": "Bendra būsena",
    "Navigation directe": "Tiesioginė navigacija"
  },
  "mt": {
    "OPUS OPS Dashboard": "Dashboard OPUS OPS",
    "OPUS OPS Operations Console": "Konsola tal-operazzjonijiet OPUS OPS",
    "Operations digest": "Sommarju tal-operazzjonijiet",
    "Health snapshot": "Stat tas-saħħa",
    "Quick access": "Aċċess rapidu",
    "Operations detail": "Dettall tal-operazzjonijiet",
    "Source summary": "Sommarju tas-sors",
    "Destination summary": "Sommarju tad-destinazzjoni",
    "Runtime diagnostics": "Dijanjostika runtime",
    "Dashboard": "Dashboard",
    "Operations": "Operazzjonijiet",
    "Command Center": "Ċentru tal-kmand",
    "Navigation": "Navigazzjoni",
    "Diagnostics": "Dijanjostika",
    "Health Hub": "Ċentru tas-saħħa",
    "Language": "Lingwa",
    "Langue": "Lingwa",
    "Choose a language": "Agħżel lingwa",
    "Choisir une langue": "Agħżel lingwa",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Sit",
    "Action": "Azzjoni",
    "Preview": "Previżjoni",
    "Aperçu": "Previżjoni",
    "Dry run": "Prova niexfa",
    "Simulation": "Prova niexfa",
    "Audit": "Awditu",
    "Open": "Iftaħ",
    "Ouvrir": "Iftaħ",
    "Run": "Ħaddem",
    "Exécuter": "Ħaddem",
    "Overview": "Ħarsa ġenerali",
    "Vue d’ensemble": "Ħarsa ġenerali",
    "Summary": "Sommarju",
    "Résumé": "Sommarju",
    "Details": "Dettalji",
    "Détails": "Dettalji",
    "Checks": "Kontrolli",
    "Contrôles": "Kontrolli",
    "Files": "Fajls",
    "Fichiers": "Fajls",
    "Version": "Verżjoni",
    "Route": "Rotta",
    "Routes": "Rotot",
    "Smoke": "Smoke test",
    "Smokes": "Smoke tests",
    "Warning": "Twissija",
    "Avertissement": "Twissija",
    "Error": "Żball",
    "Erreur": "Żball",
    "No side effects": "Mingħajr effetti sekondarji",
    "Sans effet de bord": "Mingħajr effetti sekondarji",
    "Synthèse OPS": "Sommarju OPS",
    "Console détaillée séparée": "Konsola dettaljata separata",
    "État global": "Status globali",
    "Navigation directe": "Navigazzjoni diretta"
  },
  "pl": {
    "OPUS OPS Dashboard": "Panel OPUS OPS",
    "OPUS OPS Operations Console": "Konsola operacji OPUS OPS",
    "Operations digest": "Podsumowanie operacji",
    "Health snapshot": "Stan systemu",
    "Quick access": "Szybki dostęp",
    "Operations detail": "Szczegóły operacji",
    "Source summary": "Podsumowanie źródła",
    "Destination summary": "Podsumowanie celu",
    "Runtime diagnostics": "Diagnostyka runtime",
    "Dashboard": "Panel",
    "Operations": "Operacje",
    "Command Center": "Centrum dowodzenia",
    "Navigation": "Nawigacja",
    "Diagnostics": "Diagnostyka",
    "Health Hub": "Centrum stanu",
    "Language": "Język",
    "Langue": "Język",
    "Choose a language": "Wybierz język",
    "Choisir une langue": "Wybierz język",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Witryna",
    "Action": "Akcja",
    "Preview": "Podgląd",
    "Aperçu": "Podgląd",
    "Dry run": "Próba",
    "Simulation": "Próba",
    "Audit": "Audyt",
    "Open": "Otwórz",
    "Ouvrir": "Otwórz",
    "Run": "Uruchom",
    "Exécuter": "Uruchom",
    "Overview": "Przegląd",
    "Vue d’ensemble": "Przegląd",
    "Summary": "Podsumowanie",
    "Résumé": "Podsumowanie",
    "Details": "Szczegóły",
    "Détails": "Szczegóły",
    "Checks": "Kontrole",
    "Contrôles": "Kontrole",
    "Files": "Pliki",
    "Fichiers": "Pliki",
    "Version": "Wersja",
    "Route": "Trasa",
    "Routes": "Trasy",
    "Smoke": "Smoke test",
    "Smokes": "Smoke testy",
    "Warning": "Ostrzeżenie",
    "Avertissement": "Ostrzeżenie",
    "Error": "Błąd",
    "Erreur": "Błąd",
    "No side effects": "Bez efektów ubocznych",
    "Sans effet de bord": "Bez efektów ubocznych",
    "Synthèse OPS": "Podsumowanie OPS",
    "Console détaillée séparée": "Oddzielna konsola szczegółowa",
    "État global": "Stan globalny",
    "Navigation directe": "Bezpośrednia nawigacja"
  },
  "pt": {
    "OPUS OPS Dashboard": "Painel OPUS OPS",
    "OPUS OPS Operations Console": "Consola de operações OPUS OPS",
    "Operations digest": "Resumo das operações",
    "Health snapshot": "Estado de saúde",
    "Quick access": "Acesso rápido",
    "Operations detail": "Detalhe das operações",
    "Source summary": "Resumo da origem",
    "Destination summary": "Resumo do destino",
    "Runtime diagnostics": "Diagnóstico de execução",
    "Dashboard": "Painel",
    "Operations": "Operações",
    "Command Center": "Centro de comando",
    "Navigation": "Navegação",
    "Diagnostics": "Diagnóstico",
    "Health Hub": "Centro de estado",
    "Language": "Idioma",
    "Langue": "Idioma",
    "Choose a language": "Escolha um idioma",
    "Choisir une langue": "Escolha um idioma",
    "Status": "Estado",
    "Statut": "Estado",
    "Site": "Site",
    "Action": "Ação",
    "Preview": "Pré-visualização",
    "Aperçu": "Pré-visualização",
    "Dry run": "Simulação",
    "Simulation": "Simulação",
    "Audit": "Auditoria",
    "Open": "Abrir",
    "Ouvrir": "Abrir",
    "Run": "Executar",
    "Exécuter": "Executar",
    "Overview": "Visão geral",
    "Vue d’ensemble": "Visão geral",
    "Summary": "Resumo",
    "Résumé": "Resumo",
    "Details": "Detalhes",
    "Détails": "Detalhes",
    "Checks": "Verificações",
    "Contrôles": "Verificações",
    "Files": "Ficheiros",
    "Fichiers": "Ficheiros",
    "Version": "Versão",
    "Route": "Rota",
    "Routes": "Rotas",
    "Smoke": "Smoke test",
    "Smokes": "Smoke tests",
    "Warning": "Aviso",
    "Avertissement": "Aviso",
    "Error": "Erro",
    "Erreur": "Erro",
    "No side effects": "Sem efeitos colaterais",
    "Sans effet de bord": "Sem efeitos colaterais",
    "Synthèse OPS": "Resumo OPS",
    "Console détaillée séparée": "Consola detalhada separada",
    "État global": "Estado global",
    "Navigation directe": "Navegação direta"
  },
  "ro": {
    "OPUS OPS Dashboard": "Panou OPUS OPS",
    "OPUS OPS Operations Console": "Consolă operațiuni OPUS OPS",
    "Operations digest": "Rezumat operațiuni",
    "Health snapshot": "Instantaneu stare",
    "Quick access": "Acces rapid",
    "Operations detail": "Detalii operațiuni",
    "Source summary": "Rezumat sursă",
    "Destination summary": "Rezumat destinație",
    "Runtime diagnostics": "Diagnosticare runtime",
    "Dashboard": "Panou",
    "Operations": "Operațiuni",
    "Command Center": "Centru de comandă",
    "Navigation": "Navigare",
    "Diagnostics": "Diagnosticare",
    "Health Hub": "Centru de stare",
    "Language": "Limbă",
    "Langue": "Limbă",
    "Choose a language": "Alegeți o limbă",
    "Choisir une langue": "Alegeți o limbă",
    "Status": "Stare",
    "Statut": "Stare",
    "Site": "Site",
    "Action": "Acțiune",
    "Preview": "Previzualizare",
    "Aperçu": "Previzualizare",
    "Dry run": "Rulare de probă",
    "Simulation": "Rulare de probă",
    "Audit": "Audit",
    "Open": "Deschide",
    "Ouvrir": "Deschide",
    "Run": "Rulează",
    "Exécuter": "Rulează",
    "Overview": "Prezentare generală",
    "Vue d’ensemble": "Prezentare generală",
    "Summary": "Rezumat",
    "Résumé": "Rezumat",
    "Details": "Detalii",
    "Détails": "Detalii",
    "Checks": "Verificări",
    "Contrôles": "Verificări",
    "Files": "Fișiere",
    "Fichiers": "Fișiere",
    "Version": "Versiune",
    "Route": "Rută",
    "Routes": "Rute",
    "Smoke": "Smoke test",
    "Smokes": "Smoke teste",
    "Warning": "Avertisment",
    "Avertissement": "Avertisment",
    "Error": "Eroare",
    "Erreur": "Eroare",
    "No side effects": "Fără efecte secundare",
    "Sans effet de bord": "Fără efecte secundare",
    "Synthèse OPS": "Rezumat OPS",
    "Console détaillée séparée": "Consolă detaliată separată",
    "État global": "Stare globală",
    "Navigation directe": "Navigare directă"
  },
  "sk": {
    "OPUS OPS Dashboard": "Prehľad OPUS OPS",
    "OPUS OPS Operations Console": "Konzola operácií OPUS OPS",
    "Operations digest": "Súhrn operácií",
    "Health snapshot": "Snímka stavu",
    "Quick access": "Rýchly prístup",
    "Operations detail": "Detail operácií",
    "Source summary": "Súhrn zdroja",
    "Destination summary": "Súhrn cieľa",
    "Runtime diagnostics": "Diagnostika behu",
    "Dashboard": "Prehľad",
    "Operations": "Operácie",
    "Command Center": "Riadiace centrum",
    "Navigation": "Navigácia",
    "Diagnostics": "Diagnostika",
    "Health Hub": "Centrum stavu",
    "Language": "Jazyk",
    "Langue": "Jazyk",
    "Choose a language": "Vyberte jazyk",
    "Choisir une langue": "Vyberte jazyk",
    "Status": "Stav",
    "Statut": "Stav",
    "Site": "Web",
    "Action": "Akcia",
    "Preview": "Náhľad",
    "Aperçu": "Náhľad",
    "Dry run": "Suchý beh",
    "Simulation": "Suchý beh",
    "Audit": "Audit",
    "Open": "Otvoriť",
    "Ouvrir": "Otvoriť",
    "Run": "Spustiť",
    "Exécuter": "Spustiť",
    "Overview": "Prehľad",
    "Vue d’ensemble": "Prehľad",
    "Summary": "Súhrn",
    "Résumé": "Súhrn",
    "Details": "Detaily",
    "Détails": "Detaily",
    "Checks": "Kontroly",
    "Contrôles": "Kontroly",
    "Files": "Súbory",
    "Fichiers": "Súbory",
    "Version": "Verzia",
    "Route": "Trasa",
    "Routes": "Trasy",
    "Smoke": "Smoke test",
    "Smokes": "Smoke testy",
    "Warning": "Upozornenie",
    "Avertissement": "Upozornenie",
    "Error": "Chyba",
    "Erreur": "Chyba",
    "No side effects": "Bez vedľajších účinkov",
    "Sans effet de bord": "Bez vedľajších účinkov",
    "Synthèse OPS": "Súhrn OPS",
    "Console détaillée séparée": "Samostatná detailná konzola",
    "État global": "Globálny stav",
    "Navigation directe": "Priama navigácia"
  },
  "sl": {
    "OPUS OPS Dashboard": "Pregled OPUS OPS",
    "OPUS OPS Operations Console": "Konzola operacij OPUS OPS",
    "Operations digest": "Povzetek operacij",
    "Health snapshot": "Posnetek stanja",
    "Quick access": "Hiter dostop",
    "Operations detail": "Podrobnosti operacij",
    "Source summary": "Povzetek vira",
    "Destination summary": "Povzetek cilja",
    "Runtime diagnostics": "Diagnostika izvajanja",
    "Dashboard": "Pregled",
    "Operations": "Operacije",
    "Command Center": "Nadzorni center",
    "Navigation": "Navigacija",
    "Diagnostics": "Diagnostika",
    "Health Hub": "Središče stanja",
    "Language": "Jezik",
    "Langue": "Jezik",
    "Choose a language": "Izberite jezik",
    "Choisir une langue": "Izberite jezik",
    "Status": "Stanje",
    "Statut": "Stanje",
    "Site": "Spletno mesto",
    "Action": "Dejanje",
    "Preview": "Predogled",
    "Aperçu": "Predogled",
    "Dry run": "Suhi zagon",
    "Simulation": "Suhi zagon",
    "Audit": "Revizija",
    "Open": "Odpri",
    "Ouvrir": "Odpri",
    "Run": "Zaženi",
    "Exécuter": "Zaženi",
    "Overview": "Pregled",
    "Vue d’ensemble": "Pregled",
    "Summary": "Povzetek",
    "Résumé": "Povzetek",
    "Details": "Podrobnosti",
    "Détails": "Podrobnosti",
    "Checks": "Preverjanja",
    "Contrôles": "Preverjanja",
    "Files": "Datoteke",
    "Fichiers": "Datoteke",
    "Version": "Različica",
    "Route": "Pot",
    "Routes": "Poti",
    "Smoke": "Smoke test",
    "Smokes": "Smoke testi",
    "Warning": "Opozorilo",
    "Avertissement": "Opozorilo",
    "Error": "Napaka",
    "Erreur": "Napaka",
    "No side effects": "Brez stranskih učinkov",
    "Sans effet de bord": "Brez stranskih učinkov",
    "Synthèse OPS": "Povzetek OPS",
    "Console détaillée séparée": "Ločena podrobna konzola",
    "État global": "Globalno stanje",
    "Navigation directe": "Neposredna navigacija"
  },
  "es": {
    "OPUS OPS Dashboard": "Panel OPUS OPS",
    "OPUS OPS Operations Console": "Consola de operaciones OPUS OPS",
    "Operations digest": "Resumen de operaciones",
    "Health snapshot": "Instantánea de estado",
    "Quick access": "Acceso rápido",
    "Operations detail": "Detalle de operaciones",
    "Source summary": "Resumen de origen",
    "Destination summary": "Resumen de destino",
    "Runtime diagnostics": "Diagnóstico de ejecución",
    "Dashboard": "Panel",
    "Operations": "Operaciones",
    "Command Center": "Centro de comando",
    "Navigation": "Navegación",
    "Diagnostics": "Diagnóstico",
    "Health Hub": "Centro de estado",
    "Language": "Idioma",
    "Langue": "Idioma",
    "Choose a language": "Elegir un idioma",
    "Choisir une langue": "Elegir un idioma",
    "Status": "Estado",
    "Statut": "Estado",
    "Site": "Sitio",
    "Action": "Acción",
    "Preview": "Vista previa",
    "Aperçu": "Vista previa",
    "Dry run": "Simulación",
    "Simulation": "Simulación",
    "Audit": "Auditoría",
    "Open": "Abrir",
    "Ouvrir": "Abrir",
    "Run": "Ejecutar",
    "Exécuter": "Ejecutar",
    "Overview": "Vista general",
    "Vue d’ensemble": "Vista general",
    "Summary": "Resumen",
    "Résumé": "Resumen",
    "Details": "Detalles",
    "Détails": "Detalles",
    "Checks": "Comprobaciones",
    "Contrôles": "Comprobaciones",
    "Files": "Archivos",
    "Fichiers": "Archivos",
    "Version": "Versión",
    "Route": "Ruta",
    "Routes": "Rutas",
    "Smoke": "Smoke test",
    "Smokes": "Smoke tests",
    "Warning": "Advertencia",
    "Avertissement": "Advertencia",
    "Error": "Error",
    "Erreur": "Error",
    "No side effects": "Sin efectos secundarios",
    "Sans effet de bord": "Sin efectos secundarios",
    "Synthèse OPS": "Resumen OPS",
    "Console détaillée séparée": "Consola detallada separada",
    "État global": "Estado global",
    "Navigation directe": "Navegación directa"
  },
  "sv": {
    "OPUS OPS Dashboard": "Översikt OPUS OPS",
    "OPUS OPS Operations Console": "Operationskonsol OPUS OPS",
    "Operations digest": "Operationsöversikt",
    "Health snapshot": "Hälsostatus",
    "Quick access": "Snabb åtkomst",
    "Operations detail": "Operationsdetaljer",
    "Source summary": "Källöversikt",
    "Destination summary": "Målöversikt",
    "Runtime diagnostics": "Körningsdiagnostik",
    "Dashboard": "Översikt",
    "Operations": "Operationer",
    "Command Center": "Kommandocentral",
    "Navigation": "Navigering",
    "Diagnostics": "Diagnostik",
    "Health Hub": "Hälsonav",
    "Language": "Språk",
    "Langue": "Språk",
    "Choose a language": "Välj språk",
    "Choisir une langue": "Välj språk",
    "Status": "Status",
    "Statut": "Status",
    "Site": "Webbplats",
    "Action": "Åtgärd",
    "Preview": "Förhandsvisning",
    "Aperçu": "Förhandsvisning",
    "Dry run": "Torrkörning",
    "Simulation": "Torrkörning",
    "Audit": "Revision",
    "Open": "Öppna",
    "Ouvrir": "Öppna",
    "Run": "Kör",
    "Exécuter": "Kör",
    "Overview": "Översikt",
    "Vue d’ensemble": "Översikt",
    "Summary": "Sammanfattning",
    "Résumé": "Sammanfattning",
    "Details": "Detaljer",
    "Détails": "Detaljer",
    "Checks": "Kontroller",
    "Contrôles": "Kontroller",
    "Files": "Filer",
    "Fichiers": "Filer",
    "Version": "Version",
    "Route": "Rutt",
    "Routes": "Rutter",
    "Smoke": "Smoke-test",
    "Smokes": "Smoke-tester",
    "Warning": "Varning",
    "Avertissement": "Varning",
    "Error": "Fel",
    "Erreur": "Fel",
    "No side effects": "Utan bieffekter",
    "Sans effet de bord": "Utan bieffekter",
    "Synthèse OPS": "OPS-sammanfattning",
    "Console détaillée séparée": "Separat detaljerad konsol",
    "État global": "Global status",
    "Navigation directe": "Direkt navigering"
  },
  "uk": {
    "OPUS OPS Dashboard": "Панель OPUS OPS",
    "OPUS OPS Operations Console": "Консоль операцій OPUS OPS",
    "Operations digest": "Зведення операцій",
    "Health snapshot": "Знімок стану",
    "Quick access": "Швидкий доступ",
    "Operations detail": "Деталі операцій",
    "Source summary": "Зведення джерела",
    "Destination summary": "Зведення призначення",
    "Runtime diagnostics": "Діагностика виконання",
    "Dashboard": "Панель",
    "Operations": "Операції",
    "Command Center": "Командний центр",
    "Navigation": "Навігація",
    "Diagnostics": "Діагностика",
    "Health Hub": "Центр стану",
    "Language": "Мова",
    "Langue": "Мова",
    "Choose a language": "Виберіть мову",
    "Choisir une langue": "Виберіть мову",
    "Status": "Стан",
    "Statut": "Стан",
    "Site": "Сайт",
    "Action": "Дія",
    "Preview": "Попередній перегляд",
    "Aperçu": "Попередній перегляд",
    "Dry run": "Тестовий запуск",
    "Simulation": "Тестовий запуск",
    "Audit": "Аудит",
    "Open": "Відкрити",
    "Ouvrir": "Відкрити",
    "Run": "Запустити",
    "Exécuter": "Запустити",
    "Overview": "Огляд",
    "Vue d’ensemble": "Огляд",
    "Summary": "Зведення",
    "Résumé": "Зведення",
    "Details": "Деталі",
    "Détails": "Деталі",
    "Checks": "Перевірки",
    "Contrôles": "Перевірки",
    "Files": "Файли",
    "Fichiers": "Файли",
    "Version": "Версія",
    "Route": "Маршрут",
    "Routes": "Маршрути",
    "Smoke": "Smoke тест",
    "Smokes": "Smoke тести",
    "Warning": "Попередження",
    "Avertissement": "Попередження",
    "Error": "Помилка",
    "Erreur": "Помилка",
    "No side effects": "Без побічних ефектів",
    "Sans effet de bord": "Без побічних ефектів",
    "Synthèse OPS": "Зведення OPS",
    "Console détaillée séparée": "Окрема детальна консоль",
    "État global": "Глобальний стан",
    "Navigation directe": "Пряма навігація"
  }
}
JSON, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }
}


if (!function_exists('p7ops_language_from_native_slug')) {
    function p7ops_language_from_native_slug(string $slug): ?string
    {
        $decoded = rawurldecode($slug);
        foreach (p7ops_language_options() as $code => $meta) {
            if (($meta['slug'] ?? '') === $decoded) {
                return (string) $code;
            }
        }

        return null;
    }
}

if (!function_exists('p7ops_resolve_native_route')) {
    function p7ops_resolve_native_route(string $path): ?array
    {
        $clean = trim(rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path), '/');
        if ($clean === '') {
            return null;
        }

        $segments = explode('/', $clean);
        $language = p7ops_language_from_native_slug((string) ($segments[0] ?? ''));
        if ($language === null) {
            return null;
        }

        $pageSlug = (string) ($segments[1] ?? '');
        foreach (p7ops_native_page_slugs() as $key => $definition) {
            $slugs = $definition['slugs'] ?? [];
            $candidate = (string) ($slugs[$language] ?? $slugs['en'] ?? $key);
            if ($pageSlug === '' || $pageSlug === $candidate) {
                return [
                    'lang' => $language,
                    'key' => (string) $key,
                    'canonical' => (string) $definition['canonical'],
                    'native_path' => '/' . $clean,
                ];
            }
        }

        return null;
    }
}

if (!function_exists('p7ops_language')) {
    function p7ops_language(): string
    {
        $language = strtolower((string) ($_GET['lang'] ?? ''));
        $options = p7ops_language_options();

        if ($language !== '' && array_key_exists($language, $options)) {
            return $language;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $native = p7ops_resolve_native_route($path);
            if ($native !== null) {
                return (string) $native['lang'];
            }
        }

        return 'fr';
    }
}

if (!function_exists('p7ops_current_site')) {
    function p7ops_current_site(): string
    {
        $site = trim((string) ($_GET['site'] ?? 'site-alpha'));
        return $site !== '' ? $site : 'site-alpha';
    }
}

if (!function_exists('p7ops_t')) {
    function p7ops_t(string $key, ?string $language = null): string
    {
        $catalog = p7ops_i18n_catalog();
        $locale = $language ?? p7ops_language();

        return (string) ($catalog[$locale][$key] ?? $catalog['en'][$key] ?? $catalog['fr'][$key] ?? $key);
    }
}

if (!function_exists('p7ops_canonical_key_from_path')) {
    function p7ops_canonical_key_from_path(string $path): string
    {
        $clean = rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path);
        $clean = $clean === '/' ? '/' : rtrim($clean, '/');

        foreach (p7ops_native_page_slugs() as $key => $definition) {
            $aliases = $definition['aliases'] ?? [$definition['canonical']];
            if (in_array($clean, $aliases, true)) {
                return (string) $key;
            }
        }

        $native = p7ops_resolve_native_route($clean);
        if ($native !== null) {
            return (string) $native['key'];
        }

        return 'dashboard';
    }
}

if (!function_exists('p7ops_native_path')) {
    function p7ops_native_path(string $canonicalPath, ?string $language = null): string
    {
        $lang = $language ?? p7ops_language();
        $options = p7ops_language_options();
        $pages = p7ops_native_page_slugs();
        $key = p7ops_canonical_key_from_path($canonicalPath);

        $languageSlug = (string) ($options[$lang]['slug'] ?? $options['fr']['slug']);
        $pageSlug = (string) ($pages[$key]['slugs'][$lang] ?? $pages[$key]['slugs']['en'] ?? $key);

        return '/' . $languageSlug . '/' . $pageSlug;
    }
}

if (!function_exists('p7ops_native_url')) {
    function p7ops_native_url(string $canonicalPath, ?string $language = null, ?string $site = null, array $query = []): string
    {
        $lang = $language ?? p7ops_language();
        $params = array_merge($_GET, $query);
        $params['site'] = $site ?? p7ops_current_site();
        $params['lang'] = $lang;

        return p7ops_native_path($canonicalPath, $lang) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('p7ops_language_url')) {
    function p7ops_language_url(string $path, ?string $language = null, ?string $site = null): string
    {
        return p7ops_native_url($path, $language, $site);
    }
}

if (!function_exists('p7ops_i18n_translation_map')) {
    function p7ops_i18n_translation_map(?string $language = null): array
    {
        $lang = $language ?? p7ops_language();
        $dictionary = p7ops_i18n_page_translation_dictionary();
        $map = $dictionary[$lang] ?? $dictionary['en'] ?? [];

        uksort($map, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $map;
    }
}

if (!function_exists('p7ops_i18n_should_translate')) {
    function p7ops_i18n_should_translate(): bool
    {
        if (isset($_GET['lang']) && (string) $_GET['lang'] !== '') {
            return true;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        return is_string($path) && p7ops_resolve_native_route($path) !== null;
    }
}

if (!function_exists('p7ops_i18n_translate_html')) {
    function p7ops_i18n_translate_html(string $html): string
    {
        if (!p7ops_i18n_should_translate()) {
            return $html;
        }

        $map = p7ops_i18n_translation_map();
        if ($map === []) {
            return $html;
        }

        return strtr($html, $map);
    }
}

if (!function_exists('p7ops_i18n_begin')) {
    function p7ops_i18n_begin(): void
    {
        static $started = false;
        if ($started) {
            return;
        }

        $started = true;
        ob_start(static fn(string $html): string => p7ops_i18n_translate_html($html));
    }
}

if (!function_exists('p7ops_language_selector')) {
    function p7ops_language_selector(?string $currentUri = null): string
    {
        $language = p7ops_language();
        $site = p7ops_current_site();
        $options = p7ops_language_options();
        $path = parse_url($currentUri ?? ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager'), PHP_URL_PATH);
        $route = is_string($path) && $path !== '' ? rawurldecode($path) : '/opus-lstsar-manager';
        $canonicalKey = p7ops_canonical_key_from_path($route);
        $pages = p7ops_native_page_slugs();
        $canonicalPath = (string) ($pages[$canonicalKey]['canonical'] ?? '/opus-lstsar-manager');

        $hiddenInputs = '';
        $query = $_GET;
        $query['site'] = $site;
        unset($query['lang']);

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $hiddenInputs .= '<input type="hidden" name="' . p7ops_h((string) $key) . '" value="' . p7ops_h((string) $value) . '">';
        }

        $optionHtml = '';
        foreach ($options as $code => $meta) {
            $selected = $code === $language ? ' selected' : '';
            $nativeUrl = p7ops_native_path($canonicalPath, (string) $code);
            $optionHtml .= '<option value="' . p7ops_h($code) . '" data-native-url="' . p7ops_h($nativeUrl) . '"' . $selected . '>' . p7ops_h($meta['name'] . ' — ' . strtoupper($code)) . '</option>';
        }

        $activeName = $options[$language]['name'] ?? $language;
        $action = p7ops_native_path($canonicalPath, $language);

        return ''
            . '<form method="get" action="' . p7ops_h($action) . '" class="ops-language-selector ops-language-selector--select" data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE" data-scope-contract="P7_OPS_I18N_PAGE_TRANSLATIONS_CORE" data-lang-active="' . p7ops_h($language) . '" data-site="' . p7ops_h($site) . '">'
            . '<label class="ops-language-selector__label" for="p7ops-language-select">' . p7ops_h(p7ops_t('language')) . '</label>'
            . $hiddenInputs
            . '<select id="p7ops-language-select" class="ops-language-selector__select" name="lang" aria-label="' . p7ops_h(p7ops_t('choose_language')) . '" onchange="var o=this.options[this.selectedIndex];if(o&&o.dataset&&o.dataset.nativeUrl){var fd=new FormData(this.form);fd.set(\'lang\',this.value);var qs=new URLSearchParams(fd);window.location.href=o.dataset.nativeUrl+\'?\'+qs.toString();}else{this.form.submit();}">'
            . $optionHtml
            . '</select>'
            . '<span class="ops-language-selector__active">' . p7ops_h(p7ops_t('active_language')) . ' · ' . p7ops_h($activeName) . '</span>'
            . '<noscript><button type="submit">OK</button></noscript>'
            . '<!-- legacy query marker: site=' . p7ops_h($site) . ' lang=' . p7ops_h($language) . ' site=' . p7ops_h($site) . ' -->'
            . '<!-- data-scope-contract="P7_OPS_I18N_NATIVE_URL_SLUGS_CORE" --><!-- P7_OPS_I18N_PAGE_TRANSLATIONS_CORE / real page translations / UE + Ukrainian / EU official languages + Ukrainian / native URL slugs keep accents: français español português čeština română українська ελληνικά български / lang=fr lang=en lang=uk / FR EN UK: bg hr cs da nl en et fi fr de el hu ga it lv lt mt pl pt ro sk sl es sv uk -->'
            . '</form>'
            . '<script data-contract="P7_OPS_I18N_PAGE_TRANSLATIONS_CORE">(function(){var params=new URLSearchParams(window.location.search);var lang=params.get("lang")||"fr";var site=params.get("site")||"site-alpha";document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("a[href^=\"/opus-lstsar-manager\"]").forEach(function(anchor){var href=anchor.getAttribute("href")||"";var url=new URL(href,window.location.origin);if(!url.searchParams.has("lang")){url.searchParams.set("lang",lang);}if(!url.searchParams.has("site")){url.searchParams.set("site",site);}anchor.setAttribute("href",url.pathname+"?"+url.searchParams.toString());});});})();</script>';
    }
}

p7ops_i18n_begin();

PHP;

p7tr_write($publicDir . '/language.php', $languageSource);

$routerFile = $publicDir . '/router.php';
$routerSource = p7tr_read($routerFile);
if (!str_contains($routerSource, "require_once __DIR__ . '/language.php';")) {
    $routerSource = preg_replace(
        '/<\?php\s+declare\(strict_types=1\);\s*/',
        "<?php" . PHP_EOL . "declare(strict_types=1);" . PHP_EOL . PHP_EOL . "require_once __DIR__ . '/language.php';" . PHP_EOL . PHP_EOL,
        $routerSource,
        1,
        $count
    );
    if ($count !== 1) {
        $routerSource = str_replace('<?php', "<?php" . PHP_EOL . "require_once __DIR__ . '/language.php';" . PHP_EOL, $routerSource);
    }
}

if (!str_contains($routerSource, 'p7ops_resolve_native_route($path)')) {
    $needle = "\$path = \$decodedPath === '/' ? '/' : rtrim(\$decodedPath, '/');";
    $insert = $needle . PHP_EOL . PHP_EOL
        . "\$nativeRoute = p7ops_resolve_native_route(\$path);" . PHP_EOL
        . "if (\$nativeRoute !== null) {" . PHP_EOL
        . "    \$_GET['lang'] = (string) \$nativeRoute['lang'];" . PHP_EOL
        . "    \$_GET['site'] = \$_GET['site'] ?? 'site-alpha';" . PHP_EOL
        . "    \$path = (string) \$nativeRoute['canonical'];" . PHP_EOL
        . "}" . PHP_EOL;
    $routerSource = str_replace($needle, $insert, $routerSource);
}

p7tr_write($routerFile, $routerSource);

$pageFiles = [
    $publicDir . '/index.php',
    $publicDir . '/action.php',
    $publicDir . '/command.php',
    $publicDir . '/navigation.php',
    $publicDir . '/diagnostics.php',
    $publicDir . '/health.php',
];

foreach ($pageFiles as $pageFile) {
    if (!is_file($pageFile)) {
        continue;
    }

    $source = p7tr_read($pageFile);
    if (!str_contains($source, "require_once __DIR__ . '/language.php';")) {
        $source = preg_replace(
            '/<\?php\s+declare\(strict_types=1\);\s*/',
            "<?php" . PHP_EOL . "declare(strict_types=1);" . PHP_EOL . PHP_EOL . "require_once __DIR__ . '/language.php';" . PHP_EOL . PHP_EOL,
            $source,
            1,
            $count
        );
        if ($count !== 1) {
            $source = str_replace('<?php', "<?php" . PHP_EOL . "require_once __DIR__ . '/language.php';" . PHP_EOL, $source);
        }
    }

    if (!str_contains($source, 'p7ops_language_selector(')) {
        $selectorLine = "<?= p7ops_language_selector(\$_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager') ?>" . PHP_EOL;
        $source = preg_replace('/(<main\b[^>]*>)/i', $selectorLine . '$1', $source, 1, $mainCount);
        if ($mainCount !== 1) {
            $source = preg_replace('/(<body\b[^>]*>)/i', '$1' . PHP_EOL . $selectorLine, $source, 1, $bodyCount);
            if ($bodyCount !== 1) {
                $source .= PHP_EOL . '?>' . PHP_EOL . $selectorLine;
            }
        }
    }

    p7tr_write($pageFile, $source);
}

$cssFile = $publicDir . '/ops-ui.css';
$css = is_file($cssFile) ? p7tr_read($cssFile) : '';
if (!str_contains($css, 'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE')) {
    $css .= PHP_EOL . '/* P7_OPS_I18N_PAGE_TRANSLATIONS_CORE */' . PHP_EOL;
    $css .= '.ops-language-selector--select{position:fixed;top:18px;right:18px;z-index:1000;display:flex;align-items:center;gap:.55rem;padding:.45rem .55rem;border:1px solid rgba(148,163,184,.32);border-radius:999px;background:rgba(15,23,42,.94);box-shadow:0 12px 30px rgba(0,0,0,.22);backdrop-filter:blur(10px);font-size:.82rem}' . PHP_EOL;
    $css .= '.ops-language-selector--select .ops-language-selector__label{color:#cbd5e1;white-space:nowrap;font-weight:700}' . PHP_EOL;
    $css .= '.ops-language-selector__select{max-width:12.5rem;min-width:8.5rem;border:1px solid rgba(148,163,184,.4);border-radius:999px;background:#e2e8f0;color:#0f172a;font-weight:800;padding:.35rem 2rem .35rem .7rem;cursor:pointer}' . PHP_EOL;
    $css .= '.ops-language-selector--select .ops-language-selector__active{display:none;color:#cbd5e1;white-space:nowrap}' . PHP_EOL;
    $css .= '@media (max-width:760px){.ops-language-selector--select{position:static;margin:1rem auto;max-width:calc(100% - 2rem);border-radius:1rem;flex-wrap:wrap;justify-content:center}.ops-language-selector__select{max-width:100%;min-width:12rem}.ops-language-selector--select .ops-language-selector__active{display:block;width:100%;text-align:center}}' . PHP_EOL;
}
p7tr_write($cssFile, $css);

$readmeFile = $siteDir . '/README.md';
$readme = is_file($readmeFile) ? p7tr_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readme, 'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_I18N_PAGE_TRANSLATIONS_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Adds a real visible translation layer for OPS pages when `lang` is explicit or a native URL is used.' . PHP_EOL;
    $readme .= '- Covers the 24 official EU languages + Ukrainian.' . PHP_EOL;
    $readme .= '- Translates visible OPS labels across Dashboard, Operations, Command Center, Navigation, Diagnostics and Health Hub.' . PHP_EOL;
    $readme .= '- Keeps technical values and operation identifiers unchanged.' . PHP_EOL;
    $readme .= '- Preserves native URL slugs with accents/non-Latin characters.' . PHP_EOL;
    $readme .= '- Covered by `tools/smokes/smoke_p7_ops_i18n_page_translations_core.php`.' . PHP_EOL;
}
p7tr_write($readmeFile, $readme);

echo 'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE_UPDATED' . PHP_EOL;
