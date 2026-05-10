<?php

declare(strict_types=1);

// Lightweight bootstrap — Stagehand's parser is pure PHP, so we don't need WP.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Stagehand\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
