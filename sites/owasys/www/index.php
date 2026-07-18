<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/default/autoload.php';

use Owasys\Application\Http\FrontController;

(new FrontController(
    dirname(__DIR__) . '/application',
    [
        '/build-action.php' => 'states/build/actions/build-action.php',
        '/source-action.php' => 'states/source/actions/source-action.php',
        '/structure-preview.php' => 'states/structure/actions/structure-preview.php',
    ],
    'score-page.php'
))->dispatch($_SERVER);
