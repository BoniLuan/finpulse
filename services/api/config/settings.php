<?php

declare(strict_types=1);

/** Resolves runtime settings from environment variables. */
return [
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL),
    'db' => [
        'dsn' => $_ENV['DATABASE_DSN'] ?? 'pgsql:host=db;port=5432;dbname=finpulse',
        'user' => $_ENV['DATABASE_USER'] ?? 'finpulse',
        'password' => $_ENV['DATABASE_PASSWORD'] ?? 'finpulse',
    ],
    'redis' => [
        'url' => $_ENV['REDIS_URL'] ?? 'tcp://redis:6379',
    ],
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'change-me-in-production',
        'ttl' => (int) ($_ENV['JWT_TTL'] ?? 3600),
    ],
    'rate_limit' => [
        'max' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60),
        'window' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
    ],
    'ai_worker' => [
        'url' => $_ENV['AI_WORKER_URL'] ?? 'http://ai-worker:8000',
    ],
    'bacen' => [
        'base_url' => $_ENV['BACEN_BASE_URL'] ?? 'https://api.bcb.gov.br',
        'cache_ttl' => (int) ($_ENV['BACEN_CACHE_TTL'] ?? 3600),
    ],
];
