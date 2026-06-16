<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use FinPulse\Application\Alert\CheckAlerts;
use FinPulse\Application\Alert\CreateAlert;
use FinPulse\Application\Ask\AskQuestion;
use FinPulse\Application\Auth\LoginUser;
use FinPulse\Application\Auth\RegisterUser;
use FinPulse\Application\Port\AnswerWriter;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\IntentParser;
use FinPulse\Application\Port\NotificationChannel;
use FinPulse\Application\Port\QueryLogRepository;
use FinPulse\Application\Port\TokenIssuer;
use FinPulse\Domain\Alert\AlertRepository;
use FinPulse\Domain\User\UserRepository;
use FinPulse\Infrastructure\Ai\AiWorkerClient;
use FinPulse\Infrastructure\Auth\JwtService;
use FinPulse\Infrastructure\Bacen\BacenClient;
use FinPulse\Infrastructure\Cache\RedisCache;
use FinPulse\Infrastructure\Channel\LogChannel;
use FinPulse\Infrastructure\Persistence\PdoAlertRepository;
use FinPulse\Infrastructure\Persistence\PdoQueryLogRepository;
use FinPulse\Infrastructure\Persistence\PdoUserRepository;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as RedisClient;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return static function (ContainerBuilder $builder): void {
    $settings = require __DIR__ . '/settings.php';

    $builder->addDefinitions([
        'settings' => $settings,

        // ── Logging (structured JSON to stdout) ──────────────────────────
        LoggerInterface::class => static function (): LoggerInterface {
            $logger = new Logger('api');
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

            return $logger;
        },

        // ── Infrastructure clients ───────────────────────────────────────
        PDO::class => static function () use ($settings): PDO {
            $pdo = new PDO(
                $settings['db']['dsn'],
                $settings['db']['user'],
                $settings['db']['password'],
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $pdo;
        },
        RedisClient::class => static fn (): RedisClient => new RedisClient($settings['redis']['url']),
        RedisCache::class => static fn (ContainerInterface $c): RedisCache
            => new RedisCache($c->get(RedisClient::class)),
        ClientInterface::class => static fn (): ClientInterface => new GuzzleClient(),

        // ── Ports → adapters ─────────────────────────────────────────────
        UserRepository::class => static fn (ContainerInterface $c)
            => new PdoUserRepository($c->get(PDO::class)),
        AlertRepository::class => static fn (ContainerInterface $c)
            => new PdoAlertRepository($c->get(PDO::class)),
        QueryLogRepository::class => static fn (ContainerInterface $c)
            => new PdoQueryLogRepository($c->get(PDO::class)),

        TokenIssuer::class => static fn (): TokenIssuer
            => new JwtService($settings['jwt']['secret'], $settings['jwt']['ttl']),

        IndicatorDataProvider::class => static fn (ContainerInterface $c) => new BacenClient(
            $c->get(ClientInterface::class),
            $c->get(RedisCache::class),
            $settings['bacen']['base_url'],
            $settings['bacen']['cache_ttl'],
        ),

        // AiWorkerClient implements both AI ports.
        AiWorkerClient::class => static fn (ContainerInterface $c)
            => new AiWorkerClient($c->get(ClientInterface::class), $settings['ai_worker']['url']),
        IntentParser::class => static fn (ContainerInterface $c) => $c->get(AiWorkerClient::class),
        AnswerWriter::class => static fn (ContainerInterface $c) => $c->get(AiWorkerClient::class),

        // ── Notification channels (keyed by name) ────────────────────────
        // Add 'whatsapp' => new WhatsAppChannel(...) here once implemented.
        'channels' => static fn (ContainerInterface $c): array => [
            'log' => new LogChannel($c->get(LoggerInterface::class)),
        ],
        NotificationChannel::class => static fn (ContainerInterface $c)
            => new LogChannel($c->get(LoggerInterface::class)),

        // ── Use cases ────────────────────────────────────────────────────
        AskQuestion::class => static fn (ContainerInterface $c) => new AskQuestion(
            $c->get(IntentParser::class),
            $c->get(IndicatorDataProvider::class),
            $c->get(AnswerWriter::class),
            $c->get(QueryLogRepository::class),
            new FinPulse\Domain\Finance\InvestmentCalculator(),
            new FinPulse\Domain\Finance\InflationCorrector(),
        ),
        RegisterUser::class => static fn (ContainerInterface $c)
            => new RegisterUser($c->get(UserRepository::class)),
        LoginUser::class => static fn (ContainerInterface $c)
            => new LoginUser($c->get(UserRepository::class), $c->get(TokenIssuer::class)),
        CreateAlert::class => static fn (ContainerInterface $c)
            => new CreateAlert($c->get(AlertRepository::class)),
        CheckAlerts::class => static fn (ContainerInterface $c) => new CheckAlerts(
            $c->get(AlertRepository::class),
            $c->get(IndicatorDataProvider::class),
            $c->get('channels'),
        ),
    ]);
};
