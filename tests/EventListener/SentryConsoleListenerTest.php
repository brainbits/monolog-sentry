<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests\EventListener;

use Brainbits\MonologSentry\EventListener\SentryConsoleListener;
use Exception;
use PHPUnit\Framework\MockObject\MockObject; // phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @covers \Brainbits\MonologSentry\EventListener\SentryConsoleListener
 */
final class SentryConsoleListenerTest extends TestCase
{
    use HubExpections;

    private MockObject&HubInterface $hub;
    private MockObject&LoggerInterface $logger;
    private SentryConsoleListener $listener;

    public function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->listener = new SentryConsoleListener($this->hub, $this->logger);
    }

    public function testScopeIsConfiguredForOnConsoleCommand(): void
    {
        $event = new ConsoleCommandEvent(null, new StringInput('foo'), new NullOutput());

        $this->expectHubIsConfigured($this->hub);

        $this->listener->onConsoleCommand($event);
    }

    public function testTagsDataIsConfiguredForOnConsoleCommand(): void
    {
        $event = new ConsoleCommandEvent(new HelpCommand(), new StringInput('foo'), new NullOutput());

        $this->expectHubIsConfiguredWithValues($this->hub, 'tags', ['command' => 'help']);

        $this->listener->onConsoleCommand($event);
    }

    public function testScopeIsConfiguredForOnConsoleError(): void
    {
        $event = new ConsoleErrorEvent(new StringInput('foo'), new NullOutput(), new Exception('foo'), null);

        $this->expectHubIsConfigured($this->hub);

        $this->listener->onConsoleError($event);
    }

    public function testTagsDataIsConfiguredForOnConsoleError(): void
    {
        $event = new ConsoleErrorEvent(new StringInput('foo'), new NullOutput(), new Exception('foo'), null);
        $event->setExitCode(127);

        $this->expectHubIsConfiguredWithValues($this->hub, 'tags', ['exit_code' => '127']);

        $this->listener->onConsoleError($event);
    }

    public function testLogsAreCreatedForOnConsoleError(): void
    {
        $this->logger->expects($this->atLeastOnce())
            ->method('critical');

        $event = new ConsoleErrorEvent(new StringInput('foo'), new NullOutput(), new Exception('foo'), null);

        $this->listener->onConsoleError($event);
    }

    public function testNoOnConsoleTerminateHandlingForZeroExitCode(): void
    {
        $this->logger->expects($this->never())
            ->method('critical');

        $event = new ConsoleTerminateEvent(new HelpCommand(), new StringInput('foo'), new NullOutput(), 0);

        $this->listener->onConsoleTerminate($event);
    }

    public function testLogsAreCreatedForOnConsoleTerminate(): void
    {
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('Command `help` exited with status code 100');

        $event = new ConsoleTerminateEvent(new HelpCommand(), new StringInput('foo'), new NullOutput(), 100);

        $this->listener->onConsoleTerminate($event);
    }

    public function testExitCodeIsFixedForOnConsoleTerminate(): void
    {
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('Command `help` exited with status code 255');

        $event = new ConsoleTerminateEvent(new HelpCommand(), new StringInput('foo'), new NullOutput(), 1000);

        $this->listener->onConsoleTerminate($event);
    }

    public function testCommandNameIsLoggedForOnConsoleTerminate(): void
    {
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('Command `help` exited with status code 1');

        $event = new ConsoleTerminateEvent(new HelpCommand(), new StringInput('foo'), new NullOutput(), 1);

        $this->listener->onConsoleTerminate($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            [
                ConsoleEvents::COMMAND => ['onConsoleCommand', 1],
                ConsoleEvents::ERROR => ['onConsoleError', 1],
                ConsoleEvents::TERMINATE => ['onConsoleTerminate', 1],
            ],
            SentryConsoleListener::getSubscribedEvents(),
        );
    }
}
