<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use Opus\Contract\ContractException;
use Opus\Template\ScoreTemplateRenderer;

$root = $projectRoot . '/var/tmp/p116b3_score_template_directives';
if (is_dir($root)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}

mkdir($root . '/partials', 0777, true);

file_put_contents($root . '/partials/item.score', '<span>{{ loop.index }}/{{ loop.length }} {{ key }}={{ item.label|trim|upper }} {{ item.empty|default:"fallback" }}</span>');
file_put_contents($root . '/main.score', <<<'SCORE'
Hello {{ user.name }} {{{ html }}}
[[ if: user.isAdmin ]]ADMIN[[ else ]]USER {{ missing.value }}[[ endif ]]
[[ if: missing is not defined ]]MISSING_OK[[ endif ]]
[[ if: items is not empty ]]HAS_ITEMS[[ endif ]]
[[ foreach: items as key, item ]][[ include:partials/item.score ]][[ if: loop.last ]]LAST[[ endif ]]
[[ endforeach ]]
{{ publishedAt|date:"Y-m-d" }}
{{ items|length }}
SCORE);

$renderer = new ScoreTemplateRenderer($root);
$html = $renderer->render('main.score', [
    'user' => [
        'name' => '<Steve>',
        'isAdmin' => true,
    ],
    'html' => '<strong>raw</strong>',
    'items' => [
        'first' => ['label' => ' cello ', 'empty' => ''],
        'second' => ['label' => 'violin', 'empty' => 'set'],
    ],
    'publishedAt' => '2026-06-14',
]);

$normalizedHtml = str_replace(["\r\n", "\r"], "\n", $html);

$expectedFragments = [
    'Hello &lt;Steve&gt; <strong>raw</strong>',
    'ADMIN',
    'MISSING_OK',
    'HAS_ITEMS',
    '<span>1/2 first=CELLO fallback</span>',
    '<span>2/2 second=VIOLIN set</span>LAST',
    '2026-06-14',
    "\n2",
];

foreach ($expectedFragments as $fragment) {
    if (!str_contains($normalizedHtml, $fragment)) {
        fwrite(STDERR, 'P116B3_SCORE_TEMPLATE_FRAGMENT_MISSING=' . $fragment . PHP_EOL);
        fwrite(STDERR, $normalizedHtml . PHP_EOL);
        exit(1);
    }
}

$mustFail = [
    'missing.score' => '{{ does.not.exist }}',
    'unknown_filter.score' => '{{ user.name|capitalize }}',
    'unknown_directive.score' => '[[ macro:bad ]]',
    'bad_foreach.score' => '[[ foreach: user.name as char ]]{{ char }}[[ endforeach ]]',
    'bad_extension.score' => 'invalid',
];

foreach ($mustFail as $file => $source) {
    file_put_contents($root . '/' . $file, $source);
}

$failureChecks = [
    ['missing.score', 'OPUS_SCORE_TEMPLATE_DATA_MISSING'],
    ['unknown_filter.score', 'OPUS_SCORE_TEMPLATE_UNKNOWN_FILTER'],
    ['unknown_directive.score', 'OPUS_SCORE_TEMPLATE_UNKNOWN_DIRECTIVE'],
    ['bad_foreach.score', 'OPUS_SCORE_TEMPLATE_FOREACH_NOT_ITERABLE'],
    ['bad_extension.tpl', 'OPUS_SCORE_TEMPLATE_EXTENSION_INVALID'],
    ['../outside.score', 'OPUS_SCORE_TEMPLATE_PATH_TRAVERSAL'],
];

foreach ($failureChecks as [$template, $needle]) {
    try {
        $renderer->render($template, [
            'user' => ['name' => 'Steve'],
        ]);
        fwrite(STDERR, 'P116B3_EXPECTED_FAILURE_NOT_THROWN=' . $template . PHP_EOL);
        exit(1);
    } catch (ContractException $exception) {
        if (!str_contains($exception->getMessage(), $needle)) {
            fwrite(STDERR, 'P116B3_UNEXPECTED_FAILURE=' . $template . ' :: ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }
    }
}

echo 'P116B3_SCORE_TEMPLATE_DIRECTIVES_SMOKE_OK' . PHP_EOL;
echo 'directives=include,if,else,endif,foreach,endforeach' . PHP_EOL;
echo 'filters=upper,lower,trim,default,date,length' . PHP_EOL;
echo 'loop=index,index0,first,last,length' . PHP_EOL;
