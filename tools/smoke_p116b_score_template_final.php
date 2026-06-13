<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Contract\ContractException;
use Opus\Template\ScoreTemplateRenderer;

$root = sys_get_temp_dir() . '/opus_score_template_p116b_' . bin2hex(random_bytes(4));
if (!mkdir($root . '/partials', 0775, true) && !is_dir($root . '/partials')) {
    fwrite(STDERR, 'P116B_SCORE_TEMPLATE_TMP_CREATE_FAILED' . PHP_EOL);
    exit(1);
}

file_put_contents($root . '/partials/item.score', '<li>{{ loop.index }}:{{ key }}={{ item.label|upper }}</li>');
file_put_contents($root . '/main.score', implode('', [
    '<h1>{{ title|default:"Untitled" }}</h1>',
    '<p>{{ user.name }}</p>',
    '<div>{{{ body.html }}}</div>',
    '[[ if: user.enabled ]]<strong>ON</strong>[[ else ]]<strong>OFF</strong>[[ endif ]]',
    '<ul>[[ foreach: items as key, item ]][[ include:partials/item.score ]][[ endforeach ]]</ul>',
    '<time>{{ published_at|date:"Y-m-d" }}</time>',
]));

$renderer = new ScoreTemplateRenderer($root);
$html = $renderer->render('main.score', [
    'user' => ['name' => 'Steve & Maestro', 'enabled' => true],
    'body' => ['html' => '<em>raw-ok</em>'],
    'items' => [
        'a' => ['label' => 'alpha'],
        'b' => ['label' => 'beta'],
    ],
    'published_at' => '2026-06-13 10:00:00',
]);

$expected = '<h1>Untitled</h1><p>Steve &amp; Maestro</p><div><em>raw-ok</em></div><strong>ON</strong><ul><li>1:a=ALPHA</li><li>2:b=BETA</li></ul><time>2026-06-13</time>';
if ($html !== $expected) {
    fwrite(STDERR, 'P116B_SCORE_TEMPLATE_RENDER_FAILED' . PHP_EOL);
    fwrite(STDERR, 'EXPECTED=' . $expected . PHP_EOL);
    fwrite(STDERR, 'ACTUAL=' . $html . PHP_EOL);
    cleanup($root);
    exit(1);
}

assertFails($renderer, 'main.score', ['user' => ['enabled' => true], 'items' => []], 'OPUS_SCORE_TEMPLATE_DATA_MISSING');
assertFails($renderer, '../main.score', [], 'OPUS_SCORE_TEMPLATE_PATH_INVALID');
file_put_contents($root . '/bad.score', '[[ unknown:thing ]]');
assertFails($renderer, 'bad.score', [], 'OPUS_SCORE_TEMPLATE_DIRECTIVE_UNKNOWN');
file_put_contents($root . '/php.score', '<?php echo "no"; ?>');
assertFails($renderer, 'php.score', [], 'OPUS_SCORE_TEMPLATE_PHP_FORBIDDEN');

cleanup($root);
echo 'P116B_SCORE_TEMPLATE_FINAL_SMOKE_OK' . PHP_EOL;

function assertFails(ScoreTemplateRenderer $renderer, string $template, array $data, string $expectedCode): void
{
    try {
        $renderer->render($template, $data);
    } catch (ContractException $exception) {
        if (str_contains($exception->getMessage(), $expectedCode)) {
            return;
        }
        fwrite(STDERR, 'P116B_SCORE_TEMPLATE_UNEXPECTED_ERROR=' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    fwrite(STDERR, 'P116B_SCORE_TEMPLATE_EXPECTED_FAILURE_MISSING=' . $expectedCode . PHP_EOL);
    exit(1);
}

function cleanup(string $root): void
{
    foreach (glob($root . '/partials/*.score') ?: [] as $file) {
        unlink($file);
    }
    foreach (glob($root . '/*.score') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($root . '/partials')) {
        rmdir($root . '/partials');
    }
    if (is_dir($root)) {
        rmdir($root);
    }
}
