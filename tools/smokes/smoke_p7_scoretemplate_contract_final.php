<?php
declare(strict_types=1);

use Opus\Template\ScoreTemplateRenderer;

$root = dirname(__DIR__, 2);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "CHECK_AUTOLOAD=FAIL\n");
    exit(1);
}
require_once $autoload;

echo "P7_SCORETEMPLATE_CONTRACT_FINAL_SMOKE\n";

$templateRoot = sys_get_temp_dir() . '/opus_scoretemplate_contract_' . bin2hex(random_bytes(4));
if (!mkdir($templateRoot, 0777, true) && !is_dir($templateRoot)) {
    fwrite(STDERR, "CHECK_TEMP_ROOT=FAIL\n");
    exit(1);
}

$cleanup = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $GLOBALS['cleanup']($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
};
$GLOBALS['cleanup'] = $cleanup;

$write = static function (string $name, string $content) use ($templateRoot): void {
    $path = $templateRoot . '/' . $name;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
};

$fail = static function (string $check, string $detail = '') use ($templateRoot, $cleanup): void {
    echo $check . "=FAIL" . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    $cleanup($templateRoot);
    exit(1);
};

$expectContains = static function (string $check, string $haystack, string $needle) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($check, 'missing=' . $needle);
    }
    echo $check . "=OK\n";
};

$expectNotContains = static function (string $check, string $haystack, string $needle) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($check, 'unexpected=' . $needle);
    }
    echo $check . "=OK\n";
};

$expectExceptionCode = static function (string $check, callable $callable, string $code) use ($fail): void {
    try {
        $callable();
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), $code)) {
            $fail($check, 'message=' . $exception->getMessage());
        }
        echo $check . "=OK\n";
        return;
    }
    $fail($check, 'exception_missing=' . $code);
};

try {
    $write('partial.score', 'PARTIAL={{ partial }}');
    $write('main.score', <<<'SCORE'
Hello {{ name }}
Raw {{{ raw }}}
[[ include:partial.score ]]
[[ if: user.isLogged ]]YES[[ else ]]NO[[ endif ]]
[[ foreach: items as key, item ]]{{ key }}={{ item }}:{{ loop.index }}/{{ loop.length }};[[ endforeach ]]
[[ ignore ]]
{{ missing.value }}
[[ if: missing.flag ]]BROKEN[[ endif ]]
[[ ignore: nested note ]]
{{ nested.missing }}
[[ endignore ]]
[[ endignore ]]
SCORE);

    $renderer = new ScoreTemplateRenderer($templateRoot);
    $output = $renderer->render('main.score', [
        'name' => '<Steve>',
        'raw' => '<strong>OK</strong>',
        'partial' => 'included',
        'user' => ['isLogged' => true],
        'items' => ['a' => 'A', 'b' => 'B'],
    ]);

    $expectContains('CHECK_ESCAPED_INTERPOLATION', $output, '&lt;Steve&gt;');
    $expectContains('CHECK_RAW_INTERPOLATION', $output, '<strong>OK</strong>');
    $expectContains('CHECK_INCLUDE', $output, 'PARTIAL=included');
    $expectContains('CHECK_IF_BRANCH', $output, 'YES');
    $expectContains('CHECK_FOREACH_LOOP', $output, 'a=A:1/2;b=B:2/2;');
    $expectNotContains('CHECK_IGNORE_CONTENT_NOT_RENDERED', $output, 'BROKEN');
    $expectNotContains('CHECK_IGNORE_CONTENT_NOT_EVALUATED', $output, 'missing.value');
    $expectNotContains('CHECK_NESTED_IGNORE_CONTENT_NOT_RENDERED', $output, 'nested.missing');

    $write('unexpected_endignore.score', 'Before [[ endignore ]] After');
    $expectExceptionCode(
        'CHECK_UNEXPECTED_ENDIGNORE',
        static fn () => $renderer->render('unexpected_endignore.score', []),
        'OPUS_SCORE_TEMPLATE_UNEXPECTED_ENDIGNORE'
    );

    $write('unclosed_ignore.score', 'Before [[ ignore ]] After');
    $expectExceptionCode(
        'CHECK_UNCLOSED_IGNORE',
        static fn () => $renderer->render('unclosed_ignore.score', []),
        'OPUS_SCORE_TEMPLATE_UNCLOSED_IGNORE'
    );

    $write('path_invalid.txt', 'invalid');
    $expectExceptionCode(
        'CHECK_EXTENSION_GUARD',
        static fn () => $renderer->render('path_invalid.txt', []),
        'OPUS_SCORE_TEMPLATE_EXTENSION_INVALID'
    );

    echo "P7_SCORETEMPLATE_CONTRACT_FINAL_SMOKE_OK\n";
    $cleanup($templateRoot);
    exit(0);
} catch (Throwable $exception) {
    echo "CHECK_UNEXPECTED_EXCEPTION=FAIL " . $exception->getMessage() . PHP_EOL;
    $cleanup($templateRoot);
    exit(1);
}
