<?php

declare(strict_types=1);

namespace Brainbits\MonologSentry;

use Psr\Log\LoggerInterface;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\RequestIntegration;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\HttpKernel\Kernel;

use function Sentry\init;

use const PHP_OS;
use const PHP_SAPI;
use const PHP_VERSION;

final class SentryFactory
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $symfonyEnv,
        private readonly bool $symfonyDebug,
    ) {
    }

    /**
     * @param string[] $inAppInclude
     * @param string[] $inAppExclude
     * @param string[] $prefixes
     * @param mixed[]  $tags
     */
    public function create(
        ?string $dsn,
        ?string $environment = null,
        ?string $release = null,
        ?array $inAppInclude = null,
        ?array $inAppExclude = null,
        ?array $prefixes = null,
        ?array $tags = null,
    ): HubInterface {
        init([
            'dsn' => $dsn ?: null,
            'environment' => $environment, // I.e.: staging, testing, production, etc.
            'in_app_include' => $inAppInclude ?? [],
            'in_app_exclude' => $inAppExclude ?? [],
            'prefixes' => $prefixes ?? [],
            'release' => $release,
            'default_integrations' => false,
            'send_attempts' => 1,
            'integrations' => [
                new RequestIntegration(),
                new EnvironmentIntegration(),
                new FrameContextifierIntegration($this->logger),
            ],
        ]);

        $symfonyEnv = $this->symfonyEnv;
        $symfonyDebug = $this->symfonyDebug;

        $hub = SentrySdk::getCurrentHub();
        $hub->configureScope(static function (Scope $scope) use ($tags, $symfonyEnv, $symfonyDebug): void {
            // @phpstan-ignore-next-line
            $scope->setTags([
                'php_uname' => PHP_OS,
                'php_sapi' => PHP_SAPI,
                'php_version' => PHP_VERSION,
                'framework' =>  'symfony',
                'symfony_kernel_version' => Kernel::VERSION,
                'symfony_environment' => $symfonyEnv,
                'symfony_debug' => $symfonyDebug,
            ] + ($tags ?? []));
        });

        return SentrySdk::getCurrentHub();
    }
}
