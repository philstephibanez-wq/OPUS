<?php
declare(strict_types=1);

$prefix = 'Owasys\\Application\\';
$root = __DIR__ . '/src/';

spl_autoload_register(static function (string $class) use ($prefix, $root): void {
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '') {
        return;
    }

    $file = $root . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
