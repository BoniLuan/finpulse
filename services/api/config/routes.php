<?php

declare(strict_types=1);

use FinPulse\Http\Action\AskAction;
use FinPulse\Http\Action\CreateAlertAction;
use FinPulse\Http\Action\HealthAction;
use FinPulse\Http\Action\LoginAction;
use FinPulse\Http\Action\RegisterAction;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/api/v1', function (RouteCollectorProxy $group): void {
        $group->get('/health', HealthAction::class);
        $group->post('/ask', AskAction::class);

        $group->post('/auth/register', RegisterAction::class);
        $group->post('/auth/login', LoginAction::class);

        $group->post('/alerts', CreateAlertAction::class)
            ->add(JwtAuthMiddleware::class);
    });
};
