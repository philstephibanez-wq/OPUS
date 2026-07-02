<?php
declare(strict_types=1);

$root = getcwd();
$languageFile = $root . '/sites/opus-p7-ops/public/language.php';
$readmeFile = $root . '/sites/opus-p7-ops/README.md';

if (!is_file($languageFile)) {
    fwrite(STDERR, 'P7_OPS_LANGUAGE_FILE_MISSING' . PHP_EOL);
    exit(1);
}

$source = file_get_contents($languageFile);
if ($source === false) {
    fwrite(STDERR, 'P7_OPS_LANGUAGE_FILE_READ_FAILED' . PHP_EOL);
    exit(1);
}

$additions = json_decode(<<<'JSON'
{
  "bg": {
    "Compteurs OPS": "Броячи OPS",
    "Active": "Активно",
    "Ready": "Готово",
    "Blocked": "Блокирано",
    "active": "активно",
    "ready": "готово",
    "blocked": "блокирано",
    "Operation": "Операция",
    "Source": "Източник",
    "Destination": "Дестинация",
    "overview": "преглед",
    "digest": "обобщение",
    "Dashboard overview": "Преглед на таблото",
    "Dashboard digest": "Обобщение на таблото",
    "Synthèse": "Обобщение",
    "Prochaines étapes": "Следващи стъпки",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Кратък изглед на наличните операции, без суров JSON и без дълги технически колони.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Отворете Операции за детайли, Команден център за преглед/сух тест/одит и Център за състояние за глобалната матрица."
  },
  "hr": {
    "Compteurs OPS": "OPS brojači",
    "Active": "Aktivno",
    "Ready": "Spremno",
    "Blocked": "Blokirano",
    "active": "aktivno",
    "ready": "spremno",
    "blocked": "blokirano",
    "Operation": "Operacija",
    "Source": "Izvor",
    "Destination": "Odredište",
    "overview": "pregled",
    "digest": "sažetak",
    "Dashboard overview": "Pregled nadzorne ploče",
    "Dashboard digest": "Sažetak nadzorne ploče",
    "Synthèse": "Sažetak",
    "Prochaines étapes": "Sljedeći koraci",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Kratak prikaz dostupnih operacija, bez sirovog JSON-a i dugih tehničkih stupaca.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Otvorite Operacije za detalje, Zapovjedni centar za pregled/suho pokretanje/audit i Središte stanja za globalnu matricu."
  },
  "cs": {
    "Compteurs OPS": "Počítadla OPS",
    "Active": "Aktivní",
    "Ready": "Připraveno",
    "Blocked": "Blokováno",
    "active": "aktivní",
    "ready": "připraveno",
    "blocked": "blokováno",
    "Operation": "Operace",
    "Source": "Zdroj",
    "Destination": "Cíl",
    "overview": "přehled",
    "digest": "souhrn",
    "Dashboard overview": "Přehled tabule",
    "Dashboard digest": "Souhrn tabule",
    "Synthèse": "Souhrn",
    "Prochaines étapes": "Další kroky",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Krátký pohled na dostupné operace, bez surového JSON a dlouhých technických sloupců.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Otevřete Operace pro detail, Řídicí centrum pro náhled/suchý běh/audit a Centrum stavu pro globální matici."
  },
  "da": {
    "Compteurs OPS": "OPS-tællere",
    "Active": "Aktiv",
    "Ready": "Klar",
    "Blocked": "Blokeret",
    "active": "aktiv",
    "ready": "klar",
    "blocked": "blokeret",
    "Operation": "Operation",
    "Source": "Kilde",
    "Destination": "Destination",
    "overview": "oversigt",
    "digest": "resumé",
    "Dashboard overview": "Dashboardoversigt",
    "Dashboard digest": "Dashboardresumé",
    "Synthèse": "Resumé",
    "Prochaines étapes": "Næste trin",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Kort visning af tilgængelige operationer uden rå JSON og lange tekniske kolonner.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Åbn Operationer for detaljer, Kommandocenter for forhåndsvisning/tørkørsel/audit og Sundhedshub for den globale matrix."
  },
  "nl": {
    "Compteurs OPS": "OPS-tellers",
    "Active": "Actief",
    "Ready": "Gereed",
    "Blocked": "Geblokkeerd",
    "active": "actief",
    "ready": "gereed",
    "blocked": "geblokkeerd",
    "Operation": "Operatie",
    "Source": "Bron",
    "Destination": "Bestemming",
    "overview": "overzicht",
    "digest": "samenvatting",
    "Dashboard overview": "Dashboardoverzicht",
    "Dashboard digest": "Dashboardsamenvatting",
    "Synthèse": "Samenvatting",
    "Prochaines étapes": "Volgende stappen",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Korte weergave van beschikbare operaties, zonder ruwe JSON en lange technische kolommen.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Open Operaties voor details, Commandocentrum voor preview/dry-run/audit en Gezondheidscentrum voor de globale matrix."
  },
  "en": {
    "Compteurs OPS": "OPS counters",
    "Active": "Active",
    "Ready": "Ready",
    "Blocked": "Blocked",
    "active": "active",
    "ready": "ready",
    "blocked": "blocked",
    "Operation": "Operation",
    "Source": "Source",
    "Destination": "Destination",
    "overview": "overview",
    "digest": "digest",
    "Dashboard overview": "Dashboard overview",
    "Dashboard digest": "Dashboard digest",
    "Synthèse": "Summary",
    "Prochaines étapes": "Next steps",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Short view of available operations, without raw JSON or long technical columns.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Open Operations for details, Command Center for preview/dry-run/audit, and Health Hub for the global matrix."
  },
  "et": {
    "Compteurs OPS": "OPS loendurid",
    "Active": "Aktiivne",
    "Ready": "Valmis",
    "Blocked": "Blokeeritud",
    "active": "aktiivne",
    "ready": "valmis",
    "blocked": "blokeeritud",
    "Operation": "Toiming",
    "Source": "Allikas",
    "Destination": "Sihtkoht",
    "overview": "ülevaade",
    "digest": "kokkuvõte",
    "Dashboard overview": "Juhtpaneeli ülevaade",
    "Dashboard digest": "Juhtpaneeli kokkuvõte",
    "Synthèse": "Kokkuvõte",
    "Prochaines étapes": "Järgmised sammud",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Lühivaade saadaolevatele toimingutele, ilma toore JSON-i ja pikkade tehniliste veergudeta.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Ava Toimingud detailideks, Juhtimiskeskus eelvaate/kuivkäivituse/auditi jaoks ja Tervisekeskus globaalse maatriksi jaoks."
  },
  "fi": {
    "Compteurs OPS": "OPS-laskurit",
    "Active": "Aktiivinen",
    "Ready": "Valmis",
    "Blocked": "Estetty",
    "active": "aktiivinen",
    "ready": "valmis",
    "blocked": "estetty",
    "Operation": "Toiminto",
    "Source": "Lähde",
    "Destination": "Kohde",
    "overview": "yleiskatsaus",
    "digest": "yhteenveto",
    "Dashboard overview": "Koontinäkymän yleiskatsaus",
    "Dashboard digest": "Koontinäkymän yhteenveto",
    "Synthèse": "Yhteenveto",
    "Prochaines étapes": "Seuraavat vaiheet",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Lyhyt näkymä käytettävissä olevista toiminnoista ilman raakaa JSONia ja pitkiä teknisiä sarakkeita.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Avaa Toiminnot yksityiskohtia varten, Komentokeskus esikatselua/kuiva-ajoa/auditointia varten ja Tilakeskus globaalia matriisia varten."
  },
  "fr": {
    "Compteurs OPS": "Compteurs OPS",
    "Active": "Actif",
    "Ready": "Prêt",
    "Blocked": "Bloqué",
    "active": "actif",
    "ready": "prêt",
    "blocked": "bloqué",
    "Operation": "Opération",
    "Source": "Source",
    "Destination": "Destination",
    "overview": "vue d’ensemble",
    "digest": "synthèse",
    "Dashboard overview": "Vue d’ensemble du tableau de bord",
    "Dashboard digest": "Synthèse du tableau de bord",
    "Synthèse": "Synthèse",
    "Prochaines étapes": "Prochaines étapes",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Ouvre Opérations pour le détail, Centre de commande pour aperçu/simulation/audit, Centre de santé pour la matrice globale."
  },
  "de": {
    "Compteurs OPS": "OPS-Zähler",
    "Active": "Aktiv",
    "Ready": "Bereit",
    "Blocked": "Blockiert",
    "active": "aktiv",
    "ready": "bereit",
    "blocked": "blockiert",
    "Operation": "Operation",
    "Source": "Quelle",
    "Destination": "Ziel",
    "overview": "übersicht",
    "digest": "zusammenfassung",
    "Dashboard overview": "Dashboard-Übersicht",
    "Dashboard digest": "Dashboard-Zusammenfassung",
    "Synthèse": "Zusammenfassung",
    "Prochaines étapes": "Nächste Schritte",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Kurzansicht der verfügbaren Operationen, ohne rohes JSON und lange technische Spalten.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Öffnen Sie Operationen für Details, die Befehlszentrale für Vorschau/Testlauf/Audit und die Statuszentrale für die globale Matrix."
  },
  "el": {
    "Compteurs OPS": "Μετρητές OPS",
    "Active": "Ενεργό",
    "Ready": "Έτοιμο",
    "Blocked": "Αποκλεισμένο",
    "active": "ενεργό",
    "ready": "έτοιμο",
    "blocked": "αποκλεισμένο",
    "Operation": "Λειτουργία",
    "Source": "Πηγή",
    "Destination": "Προορισμός",
    "overview": "επισκόπηση",
    "digest": "σύνοψη",
    "Dashboard overview": "Επισκόπηση πίνακα ελέγχου",
    "Dashboard digest": "Σύνοψη πίνακα ελέγχου",
    "Synthèse": "Σύνοψη",
    "Prochaines étapes": "Επόμενα βήματα",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Σύντομη προβολή των διαθέσιμων λειτουργιών, χωρίς ακατέργαστο JSON και μεγάλες τεχνικές στήλες.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Ανοίξτε τις Λειτουργίες για λεπτομέρειες, το Κέντρο εντολών για προεπισκόπηση/δοκιμαστική εκτέλεση/έλεγχο και το Κέντρο υγείας για τον συνολικό πίνακα."
  },
  "hu": {
    "Compteurs OPS": "OPS számlálók",
    "Active": "Aktív",
    "Ready": "Kész",
    "Blocked": "Blokkolva",
    "active": "aktív",
    "ready": "kész",
    "blocked": "blokkolva",
    "Operation": "Művelet",
    "Source": "Forrás",
    "Destination": "Cél",
    "overview": "áttekintés",
    "digest": "összefoglaló",
    "Dashboard overview": "Irányítópult áttekintés",
    "Dashboard digest": "Irányítópult összefoglaló",
    "Synthèse": "Összefoglaló",
    "Prochaines étapes": "Következő lépések",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Rövid nézet az elérhető műveletekről, nyers JSON és hosszú technikai oszlopok nélkül.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Nyissa meg a Műveleteket a részletekhez, a Parancsközpontot az előnézet/próbafuttatás/audit számára, és az Állapotközpontot a globális mátrixhoz."
  },
  "ga": {
    "Compteurs OPS": "Áiritheoirí OPS",
    "Active": "Gníomhach",
    "Ready": "Réidh",
    "Blocked": "Blocáilte",
    "active": "gníomhach",
    "ready": "réidh",
    "blocked": "blocáilte",
    "Operation": "Oibríocht",
    "Source": "Foinse",
    "Destination": "Ceann scríbe",
    "overview": "forbhreathnú",
    "digest": "achoimre",
    "Dashboard overview": "Forbhreathnú ar an dashboard",
    "Dashboard digest": "Achoimre ar an dashboard",
    "Synthèse": "Achoimre",
    "Prochaines étapes": "Na chéad chéimeanna eile",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Amharc gearr ar na hoibríochtaí atá ar fáil, gan JSON amh ná colúin theicniúla fhada.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Oscail Oibríochtaí le haghaidh sonraí, Lárionad ordaithe le haghaidh réamhamharc/rith thirim/iniúchadh, agus Mol sláinte don mhaitrís dhomhanda."
  },
  "it": {
    "Compteurs OPS": "Contatori OPS",
    "Active": "Attivo",
    "Ready": "Pronto",
    "Blocked": "Bloccato",
    "active": "attivo",
    "ready": "pronto",
    "blocked": "bloccato",
    "Operation": "Operazione",
    "Source": "Origine",
    "Destination": "Destinazione",
    "overview": "panoramica",
    "digest": "sintesi",
    "Dashboard overview": "Panoramica del cruscotto",
    "Dashboard digest": "Sintesi del cruscotto",
    "Synthèse": "Sintesi",
    "Prochaines étapes": "Prossimi passaggi",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Vista breve delle operazioni disponibili, senza JSON grezzo né colonne tecniche lunghe.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Apri Operazioni per i dettagli, Centro di comando per anteprima/simulazione/audit e Centro stato per la matrice globale."
  },
  "lv": {
    "Compteurs OPS": "OPS skaitītāji",
    "Active": "Aktīvs",
    "Ready": "Gatavs",
    "Blocked": "Bloķēts",
    "active": "aktīvs",
    "ready": "gatavs",
    "blocked": "bloķēts",
    "Operation": "Operācija",
    "Source": "Avots",
    "Destination": "Galamērķis",
    "overview": "pārskats",
    "digest": "kopsavilkums",
    "Dashboard overview": "Paneļa pārskats",
    "Dashboard digest": "Paneļa kopsavilkums",
    "Synthèse": "Kopsavilkums",
    "Prochaines étapes": "Nākamie soļi",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Īss pieejamo operāciju skats bez neapstrādāta JSON un garām tehniskām kolonnām.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Atveriet Operācijas detaļām, Komandu centru priekšskatījumam/sausajam testam/auditam un Stāvokļa centru globālajai matricai."
  },
  "lt": {
    "Compteurs OPS": "OPS skaitikliai",
    "Active": "Aktyvu",
    "Ready": "Paruošta",
    "Blocked": "Užblokuota",
    "active": "aktyvu",
    "ready": "paruošta",
    "blocked": "užblokuota",
    "Operation": "Operacija",
    "Source": "Šaltinis",
    "Destination": "Paskirtis",
    "overview": "apžvalga",
    "digest": "suvestinė",
    "Dashboard overview": "Skydelio apžvalga",
    "Dashboard digest": "Skydelio suvestinė",
    "Synthèse": "Suvestinė",
    "Prochaines étapes": "Kiti veiksmai",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Trumpas galimų operacijų vaizdas be neapdoroto JSON ir ilgų techninių stulpelių.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Atverkite Operacijas detalėms, Komandų centrą peržiūrai/bandomajam paleidimui/auditui ir Būsenos centrą globaliai matricai."
  },
  "mt": {
    "Compteurs OPS": "Counters OPS",
    "Active": "Attiv",
    "Ready": "Lest",
    "Blocked": "Imblukkat",
    "active": "attiv",
    "ready": "lest",
    "blocked": "imblukkat",
    "Operation": "Operazzjoni",
    "Source": "Sors",
    "Destination": "Destinazzjoni",
    "overview": "ħarsa ġenerali",
    "digest": "sommarju",
    "Dashboard overview": "Ħarsa ġenerali tad-dashboard",
    "Dashboard digest": "Sommarju tad-dashboard",
    "Synthèse": "Sommarju",
    "Prochaines étapes": "Passi li jmiss",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Veduta qasira tal-operazzjonijiet disponibbli, mingħajr JSON mhux ipproċessat u kolonni tekniċi twal.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Iftaħ Operazzjonijiet għad-dettalji, Ċentru tal-kmand għall-previżjoni/prova niexfa/awditu u Ċentru tas-saħħa għall-matriċi globali."
  },
  "pl": {
    "Compteurs OPS": "Liczniki OPS",
    "Active": "Aktywne",
    "Ready": "Gotowe",
    "Blocked": "Zablokowane",
    "active": "aktywne",
    "ready": "gotowe",
    "blocked": "zablokowane",
    "Operation": "Operacja",
    "Source": "Źródło",
    "Destination": "Cel",
    "overview": "przegląd",
    "digest": "podsumowanie",
    "Dashboard overview": "Przegląd panelu",
    "Dashboard digest": "Podsumowanie panelu",
    "Synthèse": "Podsumowanie",
    "Prochaines étapes": "Następne kroki",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Krótki widok dostępnych operacji, bez surowego JSON i długich kolumn technicznych.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Otwórz Operacje po szczegóły, Centrum dowodzenia do podglądu/próby/audytu i Centrum stanu dla macierzy globalnej."
  },
  "pt": {
    "Compteurs OPS": "Contadores OPS",
    "Active": "Ativo",
    "Ready": "Pronto",
    "Blocked": "Bloqueado",
    "active": "ativo",
    "ready": "pronto",
    "blocked": "bloqueado",
    "Operation": "Operação",
    "Source": "Origem",
    "Destination": "Destino",
    "overview": "visão geral",
    "digest": "resumo",
    "Dashboard overview": "Visão geral do painel",
    "Dashboard digest": "Resumo do painel",
    "Synthèse": "Resumo",
    "Prochaines étapes": "Próximas etapas",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Vista curta das operações disponíveis, sem JSON bruto nem colunas técnicas longas.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Abra Operações para detalhes, Centro de comando para pré-visualização/simulação/auditoria e Centro de estado para a matriz global."
  },
  "ro": {
    "Compteurs OPS": "Contoare OPS",
    "Active": "Activ",
    "Ready": "Pregătit",
    "Blocked": "Blocat",
    "active": "activ",
    "ready": "pregătit",
    "blocked": "blocat",
    "Operation": "Operațiune",
    "Source": "Sursă",
    "Destination": "Destinație",
    "overview": "prezentare generală",
    "digest": "rezumat",
    "Dashboard overview": "Prezentare generală panou",
    "Dashboard digest": "Rezumat panou",
    "Synthèse": "Rezumat",
    "Prochaines étapes": "Pașii următori",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Vedere scurtă a operațiunilor disponibile, fără JSON brut și fără coloane tehnice lungi.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Deschideți Operațiuni pentru detalii, Centru de comandă pentru previzualizare/rulare de probă/audit și Centru de stare pentru matricea globală."
  },
  "sk": {
    "Compteurs OPS": "Počítadlá OPS",
    "Active": "Aktívne",
    "Ready": "Pripravené",
    "Blocked": "Blokované",
    "active": "aktívne",
    "ready": "pripravené",
    "blocked": "blokované",
    "Operation": "Operácia",
    "Source": "Zdroj",
    "Destination": "Cieľ",
    "overview": "prehľad",
    "digest": "súhrn",
    "Dashboard overview": "Prehľad tabule",
    "Dashboard digest": "Súhrn tabule",
    "Synthèse": "Súhrn",
    "Prochaines étapes": "Ďalšie kroky",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Krátky pohľad na dostupné operácie, bez surového JSON a dlhých technických stĺpcov.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Otvorte Operácie pre detail, Riadiace centrum pre náhľad/suchý beh/audit a Centrum stavu pre globálnu maticu."
  },
  "sl": {
    "Compteurs OPS": "Števci OPS",
    "Active": "Aktivno",
    "Ready": "Pripravljeno",
    "Blocked": "Blokirano",
    "active": "aktivno",
    "ready": "pripravljeno",
    "blocked": "blokirano",
    "Operation": "Operacija",
    "Source": "Vir",
    "Destination": "Cilj",
    "overview": "pregled",
    "digest": "povzetek",
    "Dashboard overview": "Pregled nadzorne plošče",
    "Dashboard digest": "Povzetek nadzorne plošče",
    "Synthèse": "Povzetek",
    "Prochaines étapes": "Naslednji koraki",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Kratek prikaz razpoložljivih operacij, brez surovega JSON-a in dolgih tehničnih stolpcev.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Odprite Operacije za podrobnosti, Nadzorni center za predogled/suhi zagon/revizijo in Središče stanja za globalno matriko."
  },
  "es": {
    "Compteurs OPS": "Contadores OPS",
    "Active": "Activo",
    "Ready": "Listo",
    "Blocked": "Bloqueado",
    "active": "activo",
    "ready": "listo",
    "blocked": "bloqueado",
    "Operation": "Operación",
    "Source": "Origen",
    "Destination": "Destino",
    "overview": "vista general",
    "digest": "resumen",
    "Dashboard overview": "Vista general del panel",
    "Dashboard digest": "Resumen del panel",
    "Synthèse": "Resumen",
    "Prochaines étapes": "Próximos pasos",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Vista breve de las operaciones disponibles, sin JSON bruto ni columnas técnicas largas.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Abra Operaciones para el detalle, Centro de comando para vista previa/simulación/auditoría y Centro de estado para la matriz global."
  },
  "sv": {
    "Compteurs OPS": "OPS-räknare",
    "Active": "Aktiv",
    "Ready": "Klar",
    "Blocked": "Blockerad",
    "active": "aktiv",
    "ready": "klar",
    "blocked": "blockerad",
    "Operation": "Operation",
    "Source": "Källa",
    "Destination": "Destination",
    "overview": "översikt",
    "digest": "sammanfattning",
    "Dashboard overview": "Dashboardöversikt",
    "Dashboard digest": "Dashboardsammanfattning",
    "Synthèse": "Sammanfattning",
    "Prochaines étapes": "Nästa steg",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Kort vy över tillgängliga operationer, utan rå JSON och långa tekniska kolumner.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Öppna Operationer för detaljer, Kommandocentral för förhandsvisning/torrkörning/revision och Hälsonav för den globala matrisen."
  },
  "uk": {
    "Compteurs OPS": "Лічильники OPS",
    "Active": "Активно",
    "Ready": "Готово",
    "Blocked": "Заблоковано",
    "active": "активно",
    "ready": "готово",
    "blocked": "заблоковано",
    "Operation": "Операція",
    "Source": "Джерело",
    "Destination": "Призначення",
    "overview": "огляд",
    "digest": "зведення",
    "Dashboard overview": "Огляд панелі",
    "Dashboard digest": "Зведення панелі",
    "Synthèse": "Зведення",
    "Prochaines étapes": "Наступні кроки",
    "Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.": "Короткий перегляд доступних операцій, без сирого JSON і довгих технічних колонок.",
    "Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.": "Відкрийте Операції для деталей, Командний центр для попереднього перегляду/тестового запуску/аудиту та Центр стану для глобальної матриці."
  }
}
JSON, true);

if (!is_array($additions)) {
    fwrite(STDERR, 'P7_OPS_VISIBLE_TRANSLATION_ADDITIONS_INVALID' . PHP_EOL);
    exit(1);
}

$pattern = "/(function p7ops_i18n_page_translation_dictionary\(\): array\s*\{.*?json_decode\(<<<'JSON'\R)(.*?)(\RJSON, true\);)/s";
if (!preg_match($pattern, $source, $match)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_BLOCK_NOT_FOUND' . PHP_EOL);
    exit(1);
}

$dictionary = json_decode($match[2], true);
if (!is_array($dictionary)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_JSON_INVALID' . PHP_EOL);
    exit(1);
}

foreach ($additions as $language => $map) {
    if (!is_array($map)) {
        continue;
    }

    $current = isset($dictionary[$language]) && is_array($dictionary[$language]) ? $dictionary[$language] : [];
    $dictionary[$language] = array_replace($current, $map);
}

$json = json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($json)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_JSON_ENCODE_FAILED' . PHP_EOL);
    exit(1);
}

$source = preg_replace($pattern, '$1' . $json . '$3', $source, 1, $count);
if ($count !== 1 || !is_string($source)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_REPLACE_FAILED' . PHP_EOL);
    exit(1);
}

if (!str_contains($source, 'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE')) {
    $source = str_replace(
        'P7_OPS_I18N_PAGE_TRANSLATIONS_CORE / real page translations',
        'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE / completed visible labels / P7_OPS_I18N_PAGE_TRANSLATIONS_CORE / real page translations',
        $source
    );
}

if (file_put_contents($languageFile, $source) === false) {
    fwrite(STDERR, 'P7_OPS_LANGUAGE_FILE_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$readme = is_file($readmeFile) ? file_get_contents($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!is_string($readme)) {
    $readme = '# OPUS P7 OPS' . PHP_EOL;
}

if (!str_contains($readme, 'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Completes visible OPS page fragments that remained mixed after the first page-translation pass.' . PHP_EOL;
    $readme .= '- Covers counters, statuses, table headers, dashboard overview/digest, summary cards and next-step instructions.' . PHP_EOL;
    $readme .= '- Keeps technical operation identifiers, source paths and destination paths unchanged.' . PHP_EOL;
    $readme .= '- Covered by `tools/smokes/smoke_p7_ops_i18n_visible_strings_fix_core.php`.' . PHP_EOL;
}

if (file_put_contents($readmeFile, $readme) === false) {
    fwrite(STDERR, 'P7_OPS_README_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE_UPDATED' . PHP_EOL;
