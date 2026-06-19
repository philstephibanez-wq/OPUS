<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Opus\Template\ScoreTemplateRenderer;

$root = sys_get_temp_dir() . '/opus_score_ignore_' . uniqid('', true);
if (!mkdir($root, 0777, true) && !is_dir($root)) {
    fwrite(STDERR, "TEMP_ROOT_FAILED\n");
    exit(1);
}

$template = $root . '/test.score';
file_put_contents($template, "A[[ ignore: hidden ]]{{ missing.value }}[[ unknown ]]<div>ignored</div>[[ endignore ]]B");

$renderer = new ScoreTemplateRenderer($root);
$output = $renderer->render('test.score', []);

if ($output !== 'AB') {
    fwrite(STDERR, "UNEXPECTED_OUTPUT=" . $output . "\n");
    exit(2);
}

file_put_contents($template, "A[[ignore]][[ignore]]hidden[[endignore]][[endignore]]B");
$output = $renderer->render('test.score', []);
if ($output !== 'AB') {
    fwrite(STDERR, "UNEXPECTED_NESTED_OUTPUT=" . $output . "\n");
    exit(3);
}

fwrite(STDOUT, "P117SITE12O5_SCORE_IGNORE_SMOKE_OK\n");
