<?php

declare(strict_types=1);

use FinPulse\Http\Middleware\JsonErrorMiddleware;
use FinPulse\Http\Middleware\RateLimitMiddleware;
use FinPulse\Infrastructure\Cache\RedisCache;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

/** @var \Psr\Container\ContainerInterface $container */
$container = (require __DIR__ . '/../config/bootstrap.php')();

AppFactory::setContainer($container);
$app = AppFactory::create();

$settings = $container->get('settings');

// Body parsing (JSON) + routing.
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Global middleware (executed bottom-up): rate limit → JSON errors.
$app->add(new RateLimitMiddleware(
    $container->get(RedisCache::class),
    $settings['rate_limit']['max'],
    $settings['rate_limit']['window'],
));
$app->add(new JsonErrorMiddleware(
    $container->get(LoggerInterface::class),
    (bool) $settings['debug'],
));

(require __DIR__ . '/../config/routes.php')($app);

$app->run();
