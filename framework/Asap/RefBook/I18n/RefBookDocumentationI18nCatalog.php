<?php

declare(strict_types=1);

namespace ASAP\RefBook\I18n;

/**
 * PUBLIC RefBook documentation I18N catalog.
 *
 * Role:
 *   Translate ASAP RefBook source metadata into an explicit target language.
 *
 * Contract:
 *   - source text is matched exactly;
 *   - English is not silently returned for other languages;
 *   - missing translations throw ASAP_REFBOOK_DOC_TRANSLATION_MISSING;
 *   - technical identifiers remain outside this catalog.
 */
final class RefBookDocumentationI18nCatalog
{
    /**
     * @var array<string,array<string,string>>
     */
    private array $translations = [
        'Expose official RefBook examples and diagrams by stable identifier' => [
            'en' => 'Expose official RefBook examples and diagrams by stable identifier',
            'fr' => 'Expose les exemples et diagrammes officiels RefBook au moyen d’identifiants stables.',
            'es' => 'Expone ejemplos y diagramas oficiales de RefBook mediante identificadores estables.',
            'de' => 'Stellt offizielle RefBook-Beispiele und Diagramme über stabile Kennungen bereit.',
            'uk' => 'Надає офіційні приклади й діаграми RefBook через стабільні ідентифікатори.',
            'it' => 'Espone esempi e diagrammi ufficiali di RefBook tramite identificatori stabili.',
            'pl' => 'Udostępnia oficjalne przykłady i diagramy RefBook przez stabilne identyfikatory.',
            'cs' => 'Zpřístupňuje oficiální příklady a diagramy RefBook pomocí stabilních identifikátorů.',
        ],
        'Read documentation assets from DOC/refbook for the RefBook REST API without path traversal or placeholder fallback.' => [
            'en' => 'Read documentation assets from DOC/refbook for the RefBook REST API without path traversal or placeholder fallback.',
            'fr' => 'Lit les assets de documentation depuis DOC/refbook pour l’API REST RefBook, sans traversée de chemin ni placeholder de secours.',
            'es' => 'Lee assets de documentación desde DOC/refbook para la API REST RefBook, sin traversal de rutas ni placeholders de reserva.',
            'de' => 'Liest Dokumentationsassets aus DOC/refbook für die RefBook-REST-API, ohne Pfadtraversal und ohne Platzhalter-Fallback.',
            'uk' => 'Читає документаційні assets з DOC/refbook для REST API RefBook без обходу шляхів і без fallback-заглушок.',
            'it' => 'Legge gli assets documentali da DOC/refbook per l’API REST RefBook, senza path traversal né placeholder di fallback.',
            'pl' => 'Czyta zasoby dokumentacji z DOC/refbook dla REST API RefBook, bez path traversal i bez zastępczych placeholderów.',
            'cs' => 'Čte dokumentační assets z DOC/refbook pro REST API RefBook bez path traversal a bez náhradního placeholderu.',
        ],
        'Only DOC/refbook/examples/*.php and DOC/refbook/diagrams/*.mmd are exposed.' => [
            'en' => 'Only DOC/refbook/examples/*.php and DOC/refbook/diagrams/*.mmd are exposed.',
            'fr' => 'Seuls DOC/refbook/examples/*.php et DOC/refbook/diagrams/*.mmd sont exposés.',
            'es' => 'Solo se exponen DOC/refbook/examples/*.php y DOC/refbook/diagrams/*.mmd.',
            'de' => 'Nur DOC/refbook/examples/*.php und DOC/refbook/diagrams/*.mmd werden bereitgestellt.',
            'uk' => 'Відкриваються лише DOC/refbook/examples/*.php і DOC/refbook/diagrams/*.mmd.',
            'it' => 'Sono esposti solo DOC/refbook/examples/*.php e DOC/refbook/diagrams/*.mmd.',
            'pl' => 'Udostępniane są tylko DOC/refbook/examples/*.php i DOC/refbook/diagrams/*.mmd.',
            'cs' => 'Zpřístupňují se pouze DOC/refbook/examples/*.php a DOC/refbook/diagrams/*.mmd.',
        ],
        'Asset identifiers are validated and must not contain path separators.' => [
            'en' => 'Asset identifiers are validated and must not contain path separators.',
            'fr' => 'Les identifiants d’assets sont validés et ne doivent contenir aucun séparateur de chemin.',
            'es' => 'Los identificadores de assets se validan y no deben contener separadores de ruta.',
            'de' => 'Asset-Kennungen werden validiert und dürfen keine Pfadtrenner enthalten.',
            'uk' => 'Ідентифікатори assets перевіряються й не мають містити розділювачі шляхів.',
            'it' => 'Gli identificatori degli assets sono validati e non devono contenere separatori di percorso.',
            'pl' => 'Identyfikatory zasobów są walidowane i nie mogą zawierać separatorów ścieżek.',
            'cs' => 'Identifikátory assets se validují a nesmí obsahovat oddělovače cest.',
        ],
        'Duplicate asset identifiers fail explicitly.' => [
            'en' => 'Duplicate asset identifiers fail explicitly.',
            'fr' => 'Les identifiants d’assets dupliqués échouent explicitement.',
            'es' => 'Los identificadores de assets duplicados fallan explícitamente.',
            'de' => 'Doppelte Asset-Kennungen führen explizit zu einem Fehler.',
            'uk' => 'Дублікати ідентифікаторів assets завершуються явною помилкою.',
            'it' => 'Gli identificatori duplicati degli assets generano un errore esplicito.',
            'pl' => 'Zduplikowane identyfikatory zasobów powodują jawny błąd.',
            'cs' => 'Duplicitní identifikátory assets selžou explicitně.',
        ],
        'Expose the RefBook domain for documentation assets' => [
            'en' => 'Expose the RefBook domain for documentation assets',
            'fr' => 'Expose le domaine RefBook pour les assets de documentation.',
            'es' => 'Expone el dominio RefBook para los assets de documentación.',
            'de' => 'Stellt die RefBook-Domäne für Dokumentationsassets bereit.',
            'uk' => 'Відкриває домен RefBook для документаційних assets.',
            'it' => 'Espone il dominio RefBook per gli assets documentali.',
            'pl' => 'Udostępnia domenę RefBook dla zasobów dokumentacji.',
            'cs' => 'Zpřístupňuje doménu RefBook pro dokumentační assets.',
        ],
        'Returns the stable RefBook domain used by snapshot and API consumers.' => [
            'en' => 'Returns the stable RefBook domain used by snapshot and API consumers.',
            'fr' => 'Retourne le domaine RefBook stable utilisé par le snapshot et les consommateurs d’API.',
            'es' => 'Devuelve el dominio RefBook estable usado por el snapshot y los consumidores de API.',
            'de' => 'Gibt die stabile RefBook-Domäne zurück, die vom Snapshot und von API-Consumern verwendet wird.',
            'uk' => 'Повертає стабільний домен RefBook, який використовують snapshot і споживачі API.',
            'it' => 'Restituisce il dominio RefBook stabile usato dallo snapshot e dai consumer API.',
            'pl' => 'Zwraca stabilną domenę RefBook używaną przez snapshot i konsumentów API.',
            'cs' => 'Vrací stabilní doménu RefBook používanou snapshotem a konzumenty API.',
        ],
        'none' => [
            'en' => 'none',
            'fr' => 'aucun',
            'es' => 'ninguno',
            'de' => 'keine',
            'uk' => 'немає',
            'it' => 'nessuno',
            'pl' => 'brak',
            'cs' => 'žádné',
        ],
        'The returned domain is RefBook.' => [
            'en' => 'The returned domain is RefBook.',
            'fr' => 'Le domaine retourné est RefBook.',
            'es' => 'El dominio devuelto es RefBook.',
            'de' => 'Die zurückgegebene Domäne ist RefBook.',
            'uk' => 'Повернений домен — RefBook.',
            'it' => 'Il dominio restituito è RefBook.',
            'pl' => 'Zwróconą domeną jest RefBook.',
            'cs' => 'Vrácená doména je RefBook.',
        ],
        'Return all official RefBook documentation assets' => [
            'en' => 'Return all official RefBook documentation assets',
            'fr' => 'Retourne tous les assets officiels de documentation RefBook.',
            'es' => 'Devuelve todos los assets oficiales de documentación RefBook.',
            'de' => 'Gibt alle offiziellen RefBook-Dokumentationsassets zurück.',
            'uk' => 'Повертає всі офіційні документаційні assets RefBook.',
            'it' => 'Restituisce tutti gli assets ufficiali di documentazione RefBook.',
            'pl' => 'Zwraca wszystkie oficjalne zasoby dokumentacji RefBook.',
            'cs' => 'Vrací všechny oficiální dokumentační assets RefBook.',
        ],
        'Scans the official DOC/refbook examples and diagrams directories and returns a deterministic asset index.' => [
            'en' => 'Scans the official DOC/refbook examples and diagrams directories and returns a deterministic asset index.',
            'fr' => 'Scanne les dossiers officiels d’exemples et de diagrammes DOC/refbook et retourne un index d’assets déterministe.',
            'es' => 'Escanea los directorios oficiales de ejemplos y diagramas DOC/refbook y devuelve un índice determinista de assets.',
            'de' => 'Durchsucht die offiziellen DOC/refbook-Verzeichnisse für Beispiele und Diagramme und gibt einen deterministischen Asset-Index zurück.',
            'uk' => 'Сканує офіційні директорії прикладів і діаграм DOC/refbook та повертає детермінований індекс assets.',
            'it' => 'Scansiona le directory ufficiali DOC/refbook di esempi e diagrammi e restituisce un indice deterministico degli assets.',
            'pl' => 'Skanuje oficjalne katalogi przykładów i diagramów DOC/refbook i zwraca deterministyczny indeks zasobów.',
            'cs' => 'Prohledá oficiální adresáře příkladů a diagramů DOC/refbook a vrátí deterministický index assets.',
        ],
        'DOC/refbook exists.' => [
            'en' => 'DOC/refbook exists.',
            'fr' => 'DOC/refbook existe.',
            'es' => 'DOC/refbook existe.',
            'de' => 'DOC/refbook existiert.',
            'uk' => 'DOC/refbook існує.',
            'it' => 'DOC/refbook esiste.',
            'pl' => 'DOC/refbook istnieje.',
            'cs' => 'DOC/refbook existuje.',
        ],
        'Asset identifiers are unique.' => [
            'en' => 'Asset identifiers are unique.',
            'fr' => 'Les identifiants d’assets sont uniques.',
            'es' => 'Los identificadores de assets son únicos.',
            'de' => 'Asset-Kennungen sind eindeutig.',
            'uk' => 'Ідентифікатори assets унікальні.',
            'it' => 'Gli identificatori degli assets sono unici.',
            'pl' => 'Identyfikatory zasobów są unikalne.',
            'cs' => 'Identifikátory assets jsou jedinečné.',
        ],
        'Examples and diagrams are returned as machine-readable arrays.' => [
            'en' => 'Examples and diagrams are returned as machine-readable arrays.',
            'fr' => 'Les exemples et diagrammes sont retournés sous forme de tableaux lisibles par machine.',
            'es' => 'Los ejemplos y diagramas se devuelven como arrays legibles por máquina.',
            'de' => 'Beispiele und Diagramme werden als maschinenlesbare Arrays zurückgegeben.',
            'uk' => 'Приклади й діаграми повертаються як машинно-читані масиви.',
            'it' => 'Esempi e diagrammi sono restituiti come array leggibili dalla macchina.',
            'pl' => 'Przykłady i diagramy są zwracane jako tablice czytelne maszynowo.',
            'cs' => 'Příklady a diagramy se vracejí jako strojově čitelná pole.',
        ],
        'Reads documentation files from disk.' => [
            'en' => 'Reads documentation files from disk.',
            'fr' => 'Lit les fichiers de documentation depuis le disque.',
            'es' => 'Lee archivos de documentación desde disco.',
            'de' => 'Liest Dokumentationsdateien von der Festplatte.',
            'uk' => 'Читає файли документації з диска.',
            'it' => 'Legge i file di documentazione dal disco.',
            'pl' => 'Czyta pliki dokumentacji z dysku.',
            'cs' => 'Čte dokumentační soubory z disku.',
        ],
        'Return one code example by stable identifier' => [
            'en' => 'Return one code example by stable identifier',
            'fr' => 'Retourne un exemple de code par identifiant stable.',
            'es' => 'Devuelve un ejemplo de código por identificador estable.',
            'de' => 'Gibt ein Codebeispiel über eine stabile Kennung zurück.',
            'uk' => 'Повертає один приклад коду за стабільним ідентифікатором.',
            'it' => 'Restituisce un esempio di codice tramite identificatore stabile.',
            'pl' => 'Zwraca przykład kodu według stabilnego identyfikatora.',
            'cs' => 'Vrací jeden příklad kódu podle stabilního identifikátoru.',
        ],
        'Finds one PHP example asset by identifier and returns its content or null when the asset does not exist.' => [
            'en' => 'Finds one PHP example asset by identifier and returns its content or null when the asset does not exist.',
            'fr' => 'Trouve un asset d’exemple PHP par identifiant et retourne son contenu, ou null si l’asset n’existe pas.',
            'es' => 'Busca un asset de ejemplo PHP por identificador y devuelve su contenido, o null si el asset no existe.',
            'de' => 'Findet ein PHP-Beispielasset anhand der Kennung und gibt dessen Inhalt zurück, oder null, wenn das Asset nicht existiert.',
            'uk' => 'Знаходить PHP-asset прикладу за ідентифікатором і повертає його вміст або null, якщо asset не існує.',
            'it' => 'Trova un asset di esempio PHP per identificatore e restituisce il contenuto, oppure null se l’asset non esiste.',
            'pl' => 'Znajduje asset przykładu PHP według identyfikatora i zwraca jego treść albo null, jeśli asset nie istnieje.',
            'cs' => 'Najde asset příkladu PHP podle identifikátoru a vrátí jeho obsah, nebo null, pokud asset neexistuje.',
        ],
        'The identifier is a valid RefBook asset id.' => [
            'en' => 'The identifier is a valid RefBook asset id.',
            'fr' => 'L’identifiant est un id d’asset RefBook valide.',
            'es' => 'El identificador es un id de asset RefBook válido.',
            'de' => 'Die Kennung ist eine gültige RefBook-Asset-ID.',
            'uk' => 'Ідентифікатор є коректним id asset RefBook.',
            'it' => 'L’identificatore è un id asset RefBook valido.',
            'pl' => 'Identyfikator jest prawidłowym id zasobu RefBook.',
            'cs' => 'Identifikátor je platné id assetu RefBook.',
        ],
        'Returns one example payload or null.' => [
            'en' => 'Returns one example payload or null.',
            'fr' => 'Retourne un payload d’exemple ou null.',
            'es' => 'Devuelve un payload de ejemplo o null.',
            'de' => 'Gibt ein Beispiel-Payload oder null zurück.',
            'uk' => 'Повертає payload прикладу або null.',
            'it' => 'Restituisce un payload di esempio o null.',
            'pl' => 'Zwraca payload przykładu albo null.',
            'cs' => 'Vrací payload příkladu nebo null.',
        ],
        'Reads one documentation file from disk when present.' => [
            'en' => 'Reads one documentation file from disk when present.',
            'fr' => 'Lit un fichier de documentation depuis le disque lorsqu’il est présent.',
            'es' => 'Lee un archivo de documentación desde disco cuando existe.',
            'de' => 'Liest eine Dokumentationsdatei von der Festplatte, wenn sie vorhanden ist.',
            'uk' => 'Читає один файл документації з диска, якщо він існує.',
            'it' => 'Legge un file di documentazione dal disco quando presente.',
            'pl' => 'Czyta jeden plik dokumentacji z dysku, gdy istnieje.',
            'cs' => 'Čte jeden dokumentační soubor z disku, pokud existuje.',
        ],
        'Return one Mermaid diagram by stable identifier' => [
            'en' => 'Return one Mermaid diagram by stable identifier',
            'fr' => 'Retourne un diagramme Mermaid par identifiant stable.',
            'es' => 'Devuelve un diagrama Mermaid por identificador estable.',
            'de' => 'Gibt ein Mermaid-Diagramm über eine stabile Kennung zurück.',
            'uk' => 'Повертає одну діаграму Mermaid за стабільним ідентифікатором.',
            'it' => 'Restituisce un diagramma Mermaid tramite identificatore stabile.',
            'pl' => 'Zwraca diagram Mermaid według stabilnego identyfikatora.',
            'cs' => 'Vrací jeden diagram Mermaid podle stabilního identifikátoru.',
        ],
        'Finds one Mermaid diagram asset by identifier and returns its content or null when the asset does not exist.' => [
            'en' => 'Finds one Mermaid diagram asset by identifier and returns its content or null when the asset does not exist.',
            'fr' => 'Trouve un asset de diagramme Mermaid par identifiant et retourne son contenu, ou null si l’asset n’existe pas.',
            'es' => 'Busca un asset de diagrama Mermaid por identificador y devuelve su contenido, o null si el asset no existe.',
            'de' => 'Findet ein Mermaid-Diagrammasset anhand der Kennung und gibt dessen Inhalt zurück, oder null, wenn das Asset nicht existiert.',
            'uk' => 'Знаходить asset діаграми Mermaid за ідентифікатором і повертає його вміст або null, якщо asset не існує.',
            'it' => 'Trova un asset di diagramma Mermaid per identificatore e restituisce il contenuto, oppure null se l’asset non esiste.',
            'pl' => 'Znajduje asset diagramu Mermaid według identyfikatora i zwraca jego treść albo null, jeśli asset nie istnieje.',
            'cs' => 'Najde asset diagramu Mermaid podle identifikátoru a vrátí jeho obsah, nebo null, pokud asset neexistuje.',
        ],
        'Returns one diagram payload or null.' => [
            'en' => 'Returns one diagram payload or null.',
            'fr' => 'Retourne un payload de diagramme ou null.',
            'es' => 'Devuelve un payload de diagrama o null.',
            'de' => 'Gibt ein Diagramm-Payload oder null zurück.',
            'uk' => 'Повертає payload діаграми або null.',
            'it' => 'Restituisce un payload di diagramma o null.',
            'pl' => 'Zwraca payload diagramu albo null.',
            'cs' => 'Vrací payload diagramu nebo null.',
        ],
        'Represent the request path and HTTP method passed to routing and secure dispatch boundaries.' => [
            'en' => 'Represent the request path and HTTP method passed to routing and secure dispatch boundaries.',
            'fr' => 'Représente le chemin de requête et la méthode HTTP transmis aux frontières de routage et de dispatch sécurisé.',
            'es' => 'Representa la ruta de la solicitud y el método HTTP pasados a las fronteras de routing y dispatch seguro.',
            'de' => 'Repräsentiert den Anfragepfad und die HTTP-Methode, die an Routing- und sichere Dispatch-Grenzen übergeben werden.',
            'uk' => 'Представляє шлях запиту та метод HTTP, передані межам routing і безпечного dispatch.',
            'it' => 'Rappresenta il percorso della richiesta e il metodo HTTP passati ai confini di routing e dispatch sicuro.',
            'pl' => 'Reprezentuje ścieżkę żądania i metodę HTTP przekazane do granic routingu i bezpiecznego dispatchu.',
            'cs' => 'Reprezentuje cestu požadavku a metodu HTTP předané hranicím routingu a bezpečného dispatchingu.',
        ]
    ];

    public function translateSourceText(string $sourceText, string $language, string $path): string
    {
        $language = RefBookDocumentationLocale::assertSupported($language);
        $sourceText = trim($sourceText);

        if ($sourceText === '') {
            return $sourceText;
        }

        $entry = $this->translations[$sourceText] ?? null;
        if ($entry === null || !isset($entry[$language])) {
            throw RefBookDocumentationTranslationMissingException::forSourceText($language, $path, $sourceText);
        }

        return $entry[$language];
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function all(): array
    {
        return $this->translations;
    }
}
