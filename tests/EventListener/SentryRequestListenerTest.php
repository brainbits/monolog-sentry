<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests\EventListener;

use Brainbits\MonologSentry\EventListener\SentryRequestListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject; // phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(SentryRequestListener::class)]
final class SentryRequestListenerTest extends TestCase
{
    use HubExpections;

    private MockObject&HubInterface $hub;
    private MockObject&LoggerInterface $logger;
    private SentryRequestListener $listener;

    public function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->listener = new SentryRequestListener($this->hub, $this->logger, false);
    }

    public function testNoOnKernelControllerHandlingForNonMasterRequest(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new ControllerEvent(
            $kernel,
            static function (): void {
            },
            new Request(),
            null,
        );

        $this->expectHubIsNotConfigured($this->hub);

        $this->listener->onKernelController($event);
    }

    public function testNoOnKernelControllerHandlingForMissingRoute(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new ControllerEvent(
            $kernel,
            static function (): void {
            },
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectHubIsNotConfigured($this->hub);

        $this->listener->onKernelController($event);
    }

    public function testScopeIsConfiguredForOnKernelController(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new ControllerEvent(
            $kernel,
            static function (): void {
            },
            new Request([], [], ['_route' => 'foo']),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectHubIsConfigured($this->hub);

        $this->listener->onKernelController($event);
    }

    public function testTagsDataIsConfiguredForOnKernelController(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new ControllerEvent(
            $kernel,
            static function (): void {
            },
            new Request([], [], ['_route' => 'foo']),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectHubIsConfiguredWithValues($this->hub, 'tags', ['route' => 'foo']);

        $this->listener->onKernelController($event);
    }

    public function testScopeIsConfiguredForOnKernelTerminate(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new TerminateEvent($kernel, new Request(), new Response(''));

        $this->expectHubIsConfigured($this->hub);

        $this->listener->onKernelTerminate($event);
    }

    public function testTagsDataIsConfiguredForOnKernelTerminate(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new TerminateEvent(
            $kernel,
            new Request([], [], ['_route' => 'foo']),
            new Response('', 200),
        );

        $this->expectHubIsConfiguredWithValues($this->hub, 'tags', ['status_code' => '200']);

        $this->listener->onKernelTerminate($event);
    }

    public function testNoLogsAreCreatedOnHttpStatusCodeOkForOnKernelTerminate(): void
    {
        $this->logger->expects($this->never())
            ->method('error');

        $kernel = $this->createMock(Kernel::class);
        $event = new TerminateEvent(
            $kernel,
            new Request([], [], ['_route' => 'foo']),
            new Response('', Response::HTTP_OK),
        );

        $listener = new SentryRequestListener($this->hub, $this->logger, true);
        $listener->onKernelTerminate($event);
    }

    public function testLogsAreCreatedOnHttpStatusCodeInternalServerErrorForOnKernelTerminate(): void
    {
        $this->logger->expects($this->atLeastOnce())
            ->method('error')
            ->with('500 returned');

        $kernel = $this->createMock(Kernel::class);
        $event = new TerminateEvent(
            $kernel,
            new Request([], [], ['_route' => 'foo']),
            new Response('', Response::HTTP_INTERNAL_SERVER_ERROR),
        );

        $listener = new SentryRequestListener($this->hub, $this->logger, true);
        $listener->onKernelTerminate($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            [
                KernelEvents::CONTROLLER => ['onKernelController', 10000],
                KernelEvents::TERMINATE => ['onKernelTerminate', 1],
            ],
            SentryRequestListener::getSubscribedEvents(),
        );
    }
}
