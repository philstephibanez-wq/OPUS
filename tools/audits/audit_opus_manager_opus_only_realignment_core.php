<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_CORE
 *
 * Audit OPUS-only sans warning PHP.
 */

$root = dirname(__DIR__, 2);
$reportFile = $root . '/DOC/OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_AUDIT.md';

$exclude = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
];

$opusExclude = array_merge($exclude, [
    DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager' . DIRECTORY_SEPARATOR,
]);

$keywords = [
    'i18n' => ['I18n', 'I18N', 'Translator', 'Translation', 'Locale', 'Language'],
    'template' => ['Template', 'Renderer', 'Layout', 'View', '.twig', '.score'],
    'identity' => ['Identity', 'Auth', 'Session', 'Password', 'User'],
    'acl' => ['ACL', 'Acl', 'RBAC', 'Rbac', 'Permission', 'Role'],
    'fsm' => ['FSM', 'Fsm', 'StateMachine', 'State', 'Transition', 'Navigation', 'CL'],
    'composer' => ['Composer', 'Package', 'Recipe', 'Install'],
];

$opusCandidates = [];
$managerViolations = [];

function opusOnlySkip(string $path, array $excludes): bool
{
    foreach ($excludes as $exclude) {
        if (str_contains($path, $exclude)) {
            return true;
        }
    }

    return false;
}

function opusOnlyRel(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', str_replace($root, '', $path)), '/');
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = opusOnlyRel($root, $path);

    if (opusOnlySkip($path, $exclude)) {
        continue;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($extension, ['php', 'md', 'twig', 'score', 'json', 'yml', 'yaml'], true)) {
        continue;
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        continue;
    }

    if (!opusOnlySkip($path, $opusExclude)) {
        foreach ($keywords as $domain => $needles) {
            foreach ($needles as $needle) {
                if (stripos($relative, $needle) !== false || stripos($content, $needle) !== false) {
                    $opusCandidates[$domain][$relative] = true;
                    break;
                }
            }
        }
    }

    if (str_starts_with($relative, 'sites/opus-manager/')) {
        $checks = [
            'local_i18n_service' => ['class OpusManagerI18n', 'OpusManagerI18n::'],
            'local_auth_service' => ['class OpusManagerAuth', 'OpusManagerAuth::'],
            'local_credentials_service' => ['class OpusManagerCredentials', 'password_hash(', 'admin.local.php'],
            'local_navigation_registry' => ['class OpusManagerModuleRegistry', 'OpusManagerModuleRegistry::'],
            'html_in_controller' => ['$html = ', '$html .=', '<section class="', '<form ', '<article>'],
        ];

        foreach ($checks as $violation => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    $managerViolations[$violation][$relative] = true;
                    break;
                }
            }
        }
    }
}

ksort($opusCandidates);
ksort($managerViolations);

$lines = [];
$lines[] = '# OPUS Manager — OPUS-only realignment audit';
$lines[] = '';
$lines[] = 'Contrat : `OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_CORE`';
$lines[] = '';
$lines[] = '## Règle';
$lines[] = '';
$lines[] = 'OPUS Manager est une application OPUS de type AMS.';
$lines[] = '';
$lines[] = '```text';
$lines[] = 'OPUS, encore OPUS, rien qu’OPUS.';
$lines[] = '```';
$lines[] = '';
$lines[] = '## Candidats OPUS détectés';
$lines[] = '';

foreach ($keywords as $domain => $_) {
    $lines[] = '### ' . $domain;
    $lines[] = '';

    $files = array_keys($opusCandidates[$domain] ?? []);
    sort($files);

    if ($files === []) {
        $lines[] = '- Aucun candidat détecté automatiquement.';
    } else {
        foreach (array_slice($files, 0, 40) as $file) {
            $lines[] = '- `' . $file . '`';
        }
        if (count($files) > 40) {
            $lines[] = '- ... ' . (count($files) - 40) . ' autres candidats.';
        }
    }

    $lines[] = '';
}

$lines[] = '## Dérives OPUS Manager détectées';
$lines[] = '';

if ($managerViolations === []) {
    $lines[] = 'Aucune dérive détectée.';
} else {
    foreach ($managerViolations as $violation => $filesByName) {
        $lines[] = '### ' . $violation;
        $lines[] = '';
        $files = array_keys($filesByName);
        sort($files);
        foreach ($files as $file) {
            $lines[] = '- `' . $file . '`';
        }
        $lines[] = '';
    }
}

$lines[] = '## Plan de réalignement';
$lines[] = '';
$lines[] = '1. Mapper chaque dérive vers une brique OPUS réelle.';
$lines[] = '2. Remplacer le code local par adapter OPUS explicite si nécessaire.';
$lines[] = '3. Supprimer les services locaux qui recréent le framework.';
$lines[] = '4. Remplacer HTML concaténé par templates/layouts OPUS.';
$lines[] = '5. Brancher navigation sur FSM/CL OPUS.';
$lines[] = '6. Brancher login/mot de passe sur Identity + ACL/RBAC OPUS.';
$lines[] = '7. Brancher langue sur OPUS I18N.';
$lines[] = '8. Ajouter un smoke OPUS-only bloquant.';
$lines[] = '';
$lines[] = '## Statut audit';
$lines[] = '';
$lines[] = $managerViolations === [] ? '`OPUS_ONLY_AUDIT_NO_VIOLATION`' : '`OPUS_ONLY_AUDIT_VIOLATIONS_PRESENT`';
$lines[] = '';

if (file_put_contents($reportFile, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
    fwrite(STDERR, 'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_AUDIT_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'OPUS_MANAGER_OPUS_ONLY_REALIGNMENT_AUDIT_OK' . PHP_EOL;
echo $managerViolations === [] ? 'OPUS_ONLY_AUDIT_NO_VIOLATION' . PHP_EOL : 'OPUS_ONLY_AUDIT_VIOLATIONS_PRESENT' . PHP_EOL;
echo $reportFile . PHP_EOL;
