<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests;

use Brainbits\MonologSentry\SentryFactory;
use Http\Client\Common\PluginClient;
use Nyholm\NSA;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Client;
use Sentry\Dsn;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Layer;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function assert;
use function is_array;

use const PHP_OS;
use const PHP_SAPI;
use const PHP_VERSION;

/** @covers \Brainbits\MonologSentry\SentryFactory */
final class SentryFactoryTest extends TestCase
{
    public function testClientHasExpectedServices(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $factory = new SentryFactory($logger, 'testEnv', true);
        $sentry = $factory->create(
            'https://dd860622c9c84f3fa0e83ec60786fb68@o523118.ingest.sentry.io/5635040',
            '_environment',
            '_release',
            ['_sourceDir'],
            ['_cacheDir', '_vendorDir'],
            ['_projectDir'],
            ['test_string' => 'bar', 'test_number' => 123, 'test_flag' => true],
        );

        $client = $sentry->getClient();
        self::assertInstanceOf(Client::class, $client);

        $transport = NSA::getProperty($client, 'transport');
        self::assertInstanceOf(HttpTransport::class, $transport);

        $options = $client->getOptions();

        $dsn = $options->getDsn();
        self::assertInstanceOf(Dsn::class, $dsn);
        self::assertSame(
            'https://dd860622c9c84f3fa0e83ec60786fb68@o523118.ingest.sentry.io/5635040',
            $dsn->__toString(),
        );

        self::assertSame('_environment', $options->getEnvironment());
        self::assertSame('_release', $options->getRelease());
        self::assertSame(['_cacheDir', '_vendorDir'], $options->getInAppExcludedPaths());
        self::assertSame(['_sourceDir'], $options->getInAppIncludedPaths());
        self::assertSame(['_projectDir'], $options->getPrefixes());

        $pluginClient = NSA::getProperty($transport, 'httpClient');
        self::assertInstanceOf(PluginClient::class, $pluginClient);

        $httplugClient = NSA::getProperty($pluginClient, 'client');
        self::assertInstanceOf(HttplugClient::class, $httplugClient);

        $httpClient = NSA::getProperty($httplugClient, 'client');
        self::assertInstanceOf(HttpClientInterface::class, $httpClient);

        $hub = SentrySdk::getCurrentHub();
        $stack = NSA::getProperty($hub, 'stack');
        assert(is_array($stack));
        assert($stack[0] instanceof Layer);
        $layer = $stack[0];
        $scope = $layer->getScope();
        $tags = NSA::getProperty($scope, 'tags');

        self::assertSame([
            'php_uname' => PHP_OS,
            'php_sapi' => PHP_SAPI,
            'php_version' => PHP_VERSION,
            'framework' => 'symfony',
            'symfony_kernel_version' => Kernel::VERSION,
            'symfony_environment' => 'testEnv',
            'symfony_debug' => true,
            'test_string' => 'bar',
            'test_number' => 123,
            'test_flag' => true,
        ], $tags);
    }

    public function testMinimalParameters(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $factory = new SentryFactory($logger, 'testEnv', false);
        $sentry = $factory->create(null);

        $client = $sentry->getClient();
        self::assertInstanceOf(Client::class, $client);

        $transport = NSA::getProperty($client, 'transport');
        self::assertInstanceOf(NullTransport::class, $transport);

        $options = $client->getOptions();
        self::assertInstanceOf(Options::class, $options);

        self::assertNull($options->getDsn());

        self::assertNull($options->getEnvironment());
        self::assertNull($options->getRelease());
        self::assertSame([], $options->getInAppExcludedPaths());
        self::assertSame([], $options->getInAppIncludedPaths());
        self::assertSame([], $options->getPrefixes());

        $hub = SentrySdk::getCurrentHub();
        $stack = NSA::getProperty($hub, 'stack');
        assert(is_array($stack));
        assert($stack[0] instanceof Layer);
        $layer = $stack[0];
        $scope = $layer->getScope();
        $tags = NSA::getProperty($scope, 'tags');

        self::assertSame([
            'php_uname' => PHP_OS,
            'php_sapi' => PHP_SAPI,
            'php_version' => PHP_VERSION,
            'framework' => 'symfony',
            'symfony_kernel_version' => Kernel::VERSION,
            'symfony_environment' => 'testEnv',
            'symfony_debug' => false,
        ], $tags);
    }
}
