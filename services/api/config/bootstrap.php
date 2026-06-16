<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

/**
 * Builds the DI container. Shared by the HTTP front controller (public/index.php),
 * the CLI (bin/console) and the test suite.
 */
return static function (): ContainerInterface {
    $root = dirname(__DIR__);

    // Load .env for local (non-Docker) runs; Docker injects env vars directly.
    if (is_file($root . '/.env')) {
        Dotenv::createImmutable($root)->safeLoad();
    }

    $builder = new ContainerBuilder();
    (require __DIR__ . '/container.php')($builder);

    return $builder->build();
};
