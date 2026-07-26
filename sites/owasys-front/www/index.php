<?php
declare(strict_types=1);

$bootstrap = dirname(__DIR__) . '/application/default/bootstrap.php';
if (!is_file($bootstrap)) {
    throw new RuntimeException('OWASYS_FRONT_BOOTSTRAP_MISSING');
}
return require $bootstrap;
