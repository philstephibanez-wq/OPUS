<?php
declare(strict_types=1);

const P7 = 'P7A1C_BIG_TOKENIZER_EXCEPTION_PROFILER_CONTRACT_ONE_RUN';

function out(string $key, string $value): void {
    echo P7 . '_' . $key . '=' . $value . PHP_EOL;
}

function fail_run(string $message, int $code = 1): int {
    out('FAIL', $message);
    return $code;
}

function normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

function rrmdir_if_empty(string $dir): void {
    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

function safe_unlink(string $path): void {
    if (is_file($path)) {
        @unlink($path);
    }
}

function remove_old_partial_artifacts(string $root): void {
    $paths = [
        'P7A1B_ONE_RUN_TOKENIZER_BASED_FRAMEWORK_INTERFACES.zip',
        'P7A1B2_TOKENIZER_PRECHECK_COLLISION_SAFE.zip',
        'tools/audits/run_p7a1b_tokenizer_framework_interfaces.php',
        'tools/audits/run_p7a1b2_tokenizer_framework_interfaces.php',
        'tools/runners/RUN_P7A1B_ONE_RUN_TOKENIZER_BASED_FRAMEWORK_INTERFACES.cmd',
        'tools/runners/RUN_P7A1B2_TOKENIZER_PRECHECK_COLLISION_SAFE.cmd',
        'DOC/PATCHES/P7A1B_ONE_RUN_TOKENIZER_BASED_FRAMEWORK_INTERFACES.md',
        'DOC/PATCHES/P7A1B2_TOKENIZER_PRECHECK_COLLISION_SAFE.md',
    ];

    foreach ($paths as $relative) {
        safe_unlink($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }

    rrmdir_if_empty($root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'PATCHES');
    rrmdir_if_empty($root . DIRECTORY_SEPARATOR . 'DOC');
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('MKDIR_FAILED: ' . $dir);
    }
}

function list_php_files(string $root): array {
    $base = $root . DIRECTORY_SEPARATOR . 'Opus';
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $relative = normalize_path(substr($path, strlen($root) + 1));
        if ($relative === 'Opus/Fsm/Fsm.php') {
            continue;
        }
        if (str_starts_with($relative, 'Opus/Contract/PerClass/')) {
            continue;
        }
        $files[] = $path;
    }
    sort($files, SORT_STRING);
    return $files;
}

function token_text($token): string {
    return is_array($token) ? $token[1] : (string)$token;
}

function token_id($token): ?int {
    return is_array($token) ? $token[0] : null;
}

function significant_prev(array $tokens, int $idx): ?array {
    for ($i = $idx - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return [$i, $t];
    }
    return null;
}

function significant_next(array $tokens, int $idx): ?array {
    $n = count($tokens);
    for ($i = $idx + 1; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return [$i, $t];
    }
    return null;
}

function next_string_token(array $tokens, int $idx): ?array {
    $n = count($tokens);
    for ($i = $idx + 1; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if (is_array($t) && $t[0] === T_STRING) {
            return [$i, $t[1]];
        }
        return null;
    }
    return null;
}

function gather_namespace(array $tokens, int $idx): string {
    $parts = [];
    $n = count($tokens);
    for ($i = $idx + 1; $i < $n; $i++) {
        $t = $tokens[$i];
        if ($t === ';' || $t === '{') {
            break;
        }
        if (is_array($t)) {
            if (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parts[] = $t[1];
            }
            continue;
        }
        if ($t === '\\') {
            $parts[] = '\\';
        }
    }
    return trim(implode('', $parts), '\\');
}

function token_offsets(array $tokens): array {
    $offsets = [];
    $offset = 0;
    foreach ($tokens as $i => $t) {
        $offsets[$i] = $offset;
        $offset += strlen(token_text($t));
    }
    return $offsets;
}

function find_class_open_brace(array $tokens, int $classIdx): ?int {
    $n = count($tokens);
    $paren = 0;
    for ($i = $classIdx + 1; $i < $n; $i++) {
        $t = $tokens[$i];
        $text = token_text($t);
        if ($text === '(') {
            $paren++;
        } elseif ($text === ')') {
            $paren = max(0, $paren - 1);
        } elseif ($text === '{' && $paren === 0) {
            return $i;
        } elseif ($text === ';' && $paren === 0) {
            return null;
        }
    }
    return null;
}

function preceding_modifiers(array $tokens, int $idx): array {
    $mods = [];
    for ($i = $idx - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if (is_array($t) && in_array($t[0], [T_ABSTRACT, T_FINAL, T_READONLY], true)) {
            $mods[] = strtolower($t[1]);
            continue;
        }
        break;
    }
    return $mods;
}

function scan_file(string $root, string $file): array {
    $code = file_get_contents($file);
    if ($code === false) {
        throw new RuntimeException('READ_FAILED: ' . $file);
    }
    $tokens = token_get_all($code);
    $offsets = token_offsets($tokens);
    $namespace = '';
    $items = [];

    foreach ($tokens as $i => $t) {
        if (!is_array($t)) {
            continue;
        }

        if ($t[0] === T_NAMESPACE) {
            $namespace = gather_namespace($tokens, $i);
            continue;
        }

        if (!in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            continue;
        }

        $kind = $t[0] === T_CLASS ? 'class' : ($t[0] === T_INTERFACE ? 'interface' : 'trait');

        if ($kind === 'class') {
            $prev = significant_prev($tokens, $i);
            if ($prev !== null) {
                $pt = $prev[1];
                if (is_array($pt) && $pt[0] === T_NEW) {
                    continue;
                }
                if (strtolower(token_text($pt)) === 'new') {
                    continue;
                }
            }
        }

        $nameToken = next_string_token($tokens, $i);
        if ($nameToken === null) {
            if ($kind === 'class') {
                continue;
            }
            continue;
        }

        [$nameIdx, $name] = $nameToken;
        $lower = strtolower($name);
        if (in_array($lower, ['self', 'parent', 'static'], true)) {
            throw new RuntimeException('INVALID_CLASS_NAME_FROM_TOKENIZER: ' . normalize_path(substr($file, strlen($root) + 1)) . '::' . $name);
        }

        $braceIdx = $kind === 'class' ? find_class_open_brace($tokens, $i) : null;
        $mods = preceding_modifiers($tokens, $i);
        $items[] = [
            'kind' => $kind,
            'name' => $name,
            'namespace' => $namespace,
            'fqcn' => $namespace !== '' ? $namespace . '\\' . $name : $name,
            'file' => normalize_path(substr($file, strlen($root) + 1)),
            'absolute_file' => $file,
            'line' => $t[2] ?? 0,
            'abstract' => in_array('abstract', $mods, true),
            'final' => in_array('final', $mods, true),
            'class_token_offset' => $offsets[$i],
            'name_token_offset' => $offsets[$nameIdx],
            'open_brace_offset' => $braceIdx !== null ? $offsets[$braceIdx] : null,
        ];
    }

    return $items;
}

function interface_content(array $class, string $interfaceName, bool $isException): string {
    $namespace = $class['namespace'];
    $extends = [
        'OpusFrameworkComponentInterface',
        'OpusExceptionAwareInterface',
        'OpusProfilerAwareInterface',
        'OpusSelfDocumentingInterface',
    ];
    if ($isException) {
        $extends[] = 'OpusExceptionContractInterface';
    }

    $uses = [
        'use Opus\\Framework\\OpusExceptionAwareInterface;',
        'use Opus\\Framework\\OpusFrameworkComponentInterface;',
        'use Opus\\Framework\\OpusProfilerAwareInterface;',
        'use Opus\\Framework\\OpusSelfDocumentingInterface;',
    ];
    if ($isException) {
        $uses[] = 'use Opus\\Framework\\OpusExceptionContractInterface;';
    }
    sort($uses, SORT_STRING);

    $ns = $namespace !== '' ? "namespace {$namespace};\n\n" : '';
    $useBlock = implode("\n", $uses) . "\n\n";
    $extendsBlock = implode(",\n    ", $extends);

    return "<?php\n" .
        "declare(strict_types=1);\n\n" .
        $ns .
        $useBlock .
        "/**\n" .
        " * Contract interface for {$class['fqcn']}.\n" .
        " *\n" .
        " * @generated-by P7A1C_BIG_TOKENIZER_EXCEPTION_PROFILER_CONTRACT_ONE_RUN\n" .
        " *\n" .
        " * Contract:\n" .
        " * - OPUS framework component contract;\n" .
        " * - explicit exception-awareness contract;\n" .
        " * - profiler-awareness contract;\n" .
        " * - complete self-documentation contract for RefBook output.\n" .
        " */\n" .
        "interface {$interfaceName} extends\n" .
        "    {$extendsBlock}\n" .
        "{\n" .
        "}\n";
}

function framework_interface_files(): array {
    return [
        'Opus/Framework/OpusFrameworkComponentInterface.php' => "<?php\ndeclare(strict_types=1);\n\nnamespace Opus\\Framework;\n\n/**\n * Base OPUS framework component contract.\n *\n * This is intentionally a marker-level PHP contract at P7A1C so existing legacy classes can be wired without unsafe mechanical method injection.\n * The RefBook audit remains responsible for enforcing full human documentation.\n */\ninterface OpusFrameworkComponentInterface\n{\n}\n",
        'Opus/Framework/OpusExceptionAwareInterface.php' => "<?php\ndeclare(strict_types=1);\n\nnamespace Opus\\Framework;\n\n/**\n * Marks a class as participating in the OPUS error-to-exception contract.\n *\n * Runtime warnings/notices/errors must be converted or surfaced through explicit OPUS exceptions when the class has runtime behavior.\n */\ninterface OpusExceptionAwareInterface\n{\n}\n",
        'Opus/Framework/OpusProfilerAwareInterface.php' => "<?php\ndeclare(strict_types=1);\n\nnamespace Opus\\Framework;\n\n/**\n * Marks a class as visible to the OPUS profiler contract.\n *\n * Runtime exceptions and normalized throwables must carry enough context for multi-level profiler traces.\n */\ninterface OpusProfilerAwareInterface\n{\n}\n",
        'Opus/Framework/OpusSelfDocumentingInterface.php' => "<?php\ndeclare(strict_types=1);\n\nnamespace Opus\\Framework;\n\n/**\n * Marks a class as covered by the OPUS self-documentation / RefBook contract.\n *\n * Class, methods, arguments, return values, important properties and exceptions must be documented by the RefBook audit.\n */\ninterface OpusSelfDocumentingInterface\n{\n}\n",
        'Opus/Framework/OpusExceptionContractInterface.php' => "<?php\ndeclare(strict_types=1);\n\nnamespace Opus\\Framework;\n\n/**\n * Contract marker for OPUS exception classes.\n */\ninterface OpusExceptionContractInterface extends\n    OpusFrameworkComponentInterface,\n    OpusProfilerAwareInterface,\n    OpusSelfDocumentingInterface\n{\n}\n",
    ];
}

function is_exception_like(array $class, string $code): bool {
    if (str_ends_with($class['name'], 'Exception')) {
        return true;
    }
    $start = $class['class_token_offset'];
    $end = $class['open_brace_offset'] ?? min(strlen($code), $start + 300);
    $decl = substr($code, $start, max(0, $end - $start));
    return (bool)preg_match('/\bextends\s+\\\\?[\w\\\\]*Exception\b/i', $decl);
}

function should_skip_concrete_patch(array $class): ?string {
    if ($class['kind'] !== 'class') {
        return 'NOT_CLASS';
    }
    if ($class['abstract']) {
        return 'ABSTRACT_CLASS';
    }
    if ($class['open_brace_offset'] === null) {
        return 'NO_CLASS_BODY';
    }
    return null;
}

function target_interface_relative(array $class): string {
    $dir = dirname($class['file']);
    $name = $class['name'] . 'Interface.php';
    return normalize_path(($dir === '.' ? '' : $dir . '/') . $name);
}

function apply_class_patch(string $code, array $class, string $interfaceName): array {
    $open = $class['open_brace_offset'];
    if ($open === null) {
        return [false, $code, 'NO_OPEN_BRACE'];
    }

    $decl = substr($code, $class['class_token_offset'], $open - $class['class_token_offset']);
    if (preg_match('/\bimplements\b[^\\{]*\b' . preg_quote($interfaceName, '/') . '\b/', $decl)) {
        return [false, $code, 'ALREADY_IMPLEMENTS'];
    }

    $insert = preg_match('/\bimplements\b/i', $decl) ? ', ' . $interfaceName . ' ' : ' implements ' . $interfaceName . ' ';
    $new = substr($code, 0, $open) . $insert . substr($code, $open);
    return [true, $new, 'PATCHED'];
}

function lint_php_files(array $files): array {
    $errors = [];
    foreach ($files as $file) {
        $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        if ($code !== 0) {
            $errors[] = [
                'file' => $file,
                'output' => implode("\n", $output),
            ];
        }
    }
    return $errors;
}

function write_json_report(string $root, array $report): void {
    $dir = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'reference' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'json';
    ensure_dir($dir);
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'p7a1c_contract_map.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function write_markdown_report(string $root, array $report): void {
    $dir = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'reference' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'markdown';
    ensure_dir($dir);
    $lines = [];
    $lines[] = '# P7A1C contract map';
    $lines[] = '';
    $lines[] = '```text';
    $lines[] = 'classes_total=' . $report['summary']['classes_total'];
    $lines[] = 'classes_concrete=' . $report['summary']['classes_concrete'];
    $lines[] = 'classes_abstract_exempt=' . $report['summary']['classes_abstract_exempt'];
    $lines[] = 'interfaces_created=' . $report['summary']['interfaces_created'];
    $lines[] = 'classes_patched=' . $report['summary']['classes_patched'];
    $lines[] = 'existing_interfaces_preserved=' . $report['summary']['existing_interfaces_preserved'];
    $lines[] = 'php_lint_errors=' . $report['summary']['php_lint_errors'];
    $lines[] = '```';
    $lines[] = '';
    $lines[] = '## Classes';
    $lines[] = '';
    foreach ($report['classes'] as $class) {
        $lines[] = '- `' . $class['fqcn'] . '` -> `' . ($class['target_interface'] ?? '') . '` [' . ($class['status'] ?? '') . ']';
    }
    $lines[] = '';
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'P7A1C_CONTRACT_MAP.md', implode("\n", $lines));
}

function main(): int {
    $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if ($root === false) {
        return fail_run('ROOT_NOT_FOUND');
    }

    remove_old_partial_artifacts($root);

    if (is_file($root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm' . DIRECTORY_SEPARATOR . 'Fsm.php')) {
        return fail_run('DEMO_FSM_RESTORED');
    }

    $files = list_php_files($root);
    if (count($files) === 0) {
        return fail_run('PHP_FILES_ZERO');
    }

    $all = [];
    foreach ($files as $file) {
        foreach (scan_file($root, $file) as $item) {
            $all[] = $item;
        }
    }

    $classes = array_values(array_filter($all, static fn(array $x): bool => $x['kind'] === 'class'));
    $interfaces = array_values(array_filter($all, static fn(array $x): bool => $x['kind'] === 'interface'));
    $traits = array_values(array_filter($all, static fn(array $x): bool => $x['kind'] === 'trait'));

    if (count($classes) === 0) {
        return fail_run('CLASSES_ZERO');
    }

    $concrete = [];
    $exempt = [];
    foreach ($classes as $class) {
        $skip = should_skip_concrete_patch($class);
        if ($skip !== null) {
            $class['status'] = 'EXEMPT_' . $skip;
            $exempt[] = $class;
            continue;
        }
        $concrete[] = $class;
    }

    if (count($concrete) === 0) {
        return fail_run('CONCRETE_CLASSES_ZERO');
    }

    $targetMap = [];
    $collisions = [];
    foreach ($concrete as $class) {
        $target = target_interface_relative($class);
        if (!isset($targetMap[$target])) {
            $targetMap[$target] = [];
        }
        $targetMap[$target][] = $class['fqcn'];
    }

    foreach ($targetMap as $target => $fqcnList) {
        if (count($fqcnList) > 1) {
            $collisions[$target] = $fqcnList;
        }
    }

    if ($collisions !== []) {
        $parts = [];
        foreach ($collisions as $target => $fqcnList) {
            $parts[] = $target . ':' . implode('|', $fqcnList);
        }
        return fail_run('INTERFACE_NAME_COLLISION:' . implode(';', $parts));
    }

    $writes = [];
    $classFilePatches = [];
    $existingPreserved = 0;
    $createdInterfaces = 0;

    foreach (framework_interface_files() as $relative => $content) {
        $writes[$relative] = $content;
    }

    foreach ($concrete as $idx => $class) {
        $relativeInterface = target_interface_relative($class);
        $absoluteInterface = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeInterface);
        $interfaceName = $class['name'] . 'Interface';
        $class['target_interface'] = $relativeInterface;
        $class['interface_name'] = $interfaceName;

        if (is_file($absoluteInterface)) {
            $existing = file_get_contents($absoluteInterface);
            if ($existing === false) {
                return fail_run('READ_EXISTING_INTERFACE_FAILED:' . $relativeInterface);
            }
            if (!preg_match('/\binterface\s+' . preg_quote($interfaceName, '/') . '\b/', $existing)) {
                return fail_run('INTERFACE_FILE_EXISTS_WITH_DIFFERENT_DECLARATION:' . $relativeInterface);
            }
            $existingPreserved++;
            $class['interface_write'] = 'EXISTING_PRESERVED';
        } else {
            $classCode = file_get_contents($class['absolute_file']);
            if ($classCode === false) {
                return fail_run('READ_CLASS_FAILED:' . $class['file']);
            }
            $writes[$relativeInterface] = interface_content($class, $interfaceName, is_exception_like($class, $classCode));
            $createdInterfaces++;
            $class['interface_write'] = 'CREATED';
        }

        $file = $class['absolute_file'];
        if (!isset($classFilePatches[$file])) {
            $classFilePatches[$file] = [];
        }
        $classFilePatches[$file][] = $class;
        $concrete[$idx] = $class;
    }

    $patchedClassCount = 0;
    $backup = [];
    $newFiles = [];

    try {
        $patchedFileContents = [];
        foreach ($classFilePatches as $file => $fileClasses) {
            $code = file_get_contents($file);
            if ($code === false) {
                throw new RuntimeException('READ_CLASS_FILE_FAILED:' . $file);
            }

            usort($fileClasses, static fn(array $a, array $b): int => $b['open_brace_offset'] <=> $a['open_brace_offset']);
            $changed = false;
            foreach ($fileClasses as $class) {
                [$didPatch, $code, $status] = apply_class_patch($code, $class, $class['interface_name']);
                if ($didPatch) {
                    $patchedClassCount++;
                    $changed = true;
                }
            }
            if ($changed) {
                $patchedFileContents[$file] = $code;
            }
        }

        foreach ($writes as $relative => $content) {
            $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($abs)) {
                $old = file_get_contents($abs);
                if ($old === false) {
                    throw new RuntimeException('BACKUP_READ_FAILED:' . $relative);
                }
                $backup[$abs] = $old;
            } else {
                $newFiles[] = $abs;
            }
        }

        foreach ($patchedFileContents as $abs => $content) {
            if (!isset($backup[$abs])) {
                $old = file_get_contents($abs);
                if ($old === false) {
                    throw new RuntimeException('BACKUP_CLASS_READ_FAILED:' . $abs);
                }
                $backup[$abs] = $old;
            }
        }

        foreach ($writes as $relative => $content) {
            $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            ensure_dir(dirname($abs));
            file_put_contents($abs, $content);
        }

        foreach ($patchedFileContents as $abs => $content) {
            file_put_contents($abs, $content);
        }

        $lintFiles = list_php_files($root);
        $lintErrors = lint_php_files($lintFiles);

        $reportClasses = [];
        foreach ($concrete as $class) {
            $reportClasses[] = [
                'fqcn' => $class['fqcn'],
                'file' => $class['file'],
                'target_interface' => $class['target_interface'],
                'status' => 'CONCRETE_INTERFACE_AND_IMPLEMENTS_REQUIRED',
                'interface_write' => $class['interface_write'],
            ];
        }
        foreach ($exempt as $class) {
            $reportClasses[] = [
                'fqcn' => $class['fqcn'],
                'file' => $class['file'],
                'target_interface' => null,
                'status' => $class['status'],
            ];
        }

        $report = [
            'schema' => 'P7A1C_BIG_TOKENIZER_EXCEPTION_PROFILER_CONTRACT_MAP_V1',
            'summary' => [
                'php_files' => count($files),
                'class_like_total' => count($all),
                'classes_total' => count($classes),
                'classes_concrete' => count($concrete),
                'classes_abstract_exempt' => count($exempt),
                'interfaces_existing_total' => count($interfaces),
                'traits_total' => count($traits),
                'interfaces_created' => $createdInterfaces,
                'classes_patched' => $patchedClassCount,
                'existing_interfaces_preserved' => $existingPreserved,
                'php_lint_errors' => count($lintErrors),
            ],
            'classes' => $reportClasses,
            'lint_errors' => $lintErrors,
        ];

        write_json_report($root, $report);
        write_markdown_report($root, $report);

        if ($lintErrors !== []) {
            foreach ($backup as $abs => $content) {
                file_put_contents($abs, $content);
            }
            foreach ($newFiles as $abs) {
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
            out('ROLLBACK', 'OK');
            return fail_run('PHP_LINT_FAILED:' . count($lintErrors));
        }

        out('PHP_FILES', (string)count($files));
        out('CLASS_LIKE_TOTAL', (string)count($all));
        out('CLASSES_TOTAL', (string)count($classes));
        out('CONCRETE_CLASSES', (string)count($concrete));
        out('ABSTRACT_EXEMPT', (string)count($exempt));
        out('INTERFACES_CREATED', (string)$createdInterfaces);
        out('CLASSES_PATCHED', (string)$patchedClassCount);
        out('EXISTING_INTERFACES_PRESERVED', (string)$existingPreserved);
        out('MISSING_IMPLEMENTS', '0');
        out('PHP_LINT_ERRORS', '0');
        out('REPORT_JSON', 'DOC\\reference\\generated\\json\\p7a1c_contract_map.json');
        out('REPORT_MD', 'DOC\\reference\\generated\\markdown\\P7A1C_CONTRACT_MAP.md');
        out('OK', '1');
        return 0;
    } catch (Throwable $e) {
        foreach ($backup as $abs => $content) {
            @file_put_contents($abs, $content);
        }
        foreach ($newFiles as $abs) {
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        out('ROLLBACK', 'OK');
        return fail_run(get_class($e) . ':' . $e->getMessage());
    }
}

exit(main());
