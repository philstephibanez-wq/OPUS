<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/default/autoload.php';

use Owasys\Application\Http\FrontController;

(new FrontController(
    dirname(__DIR__) . '/application/default/http',
    [
        '/build-action.php' => 'build-action.php',
        '/source-action.php' => 'source-action.php',
        '/structure-preview.php' => 'structure-preview.php',
    ]
))->dispatch($_SERVER);
