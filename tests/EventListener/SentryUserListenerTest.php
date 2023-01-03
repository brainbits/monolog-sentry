<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests\EventListener;

use Brainbits\MonologSentry\EventListener\SentryUserListener;
use PHPUnit\Framework\MockObject\MockObject; // phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;
use Sentry\UserDataBag;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @covers \Brainbits\MonologSentry\EventListener\SentryUserListener
 */
final class SentryUserListenerTest extends TestCase
{
    use HubExpections;

    private MockObject&HubInterface $hub;
    private MockObject&Security $security;
    private SentryUserListener $listener;

    public function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->listener = new SentryUserListener($this->hub, $this->security);
    }

    public function testNoOnKernelRequestEventHandlingForNonMasterRequest(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new RequestEvent($kernel, new Request(), null);

        $this->expectHubIsNotConfigured($this->hub);

        $this->listener->onKernelRequest($event);
    }

    public function testScopeIsConfigured(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $event = new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->expectHubIsConfigured($this->hub);

        $this->listener->onKernelRequest($event);
    }

    public function testUserDataIsConfigured(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->security->expects($this->atLeastOnce())
            ->method('getUser')
            ->willReturn(new InMemoryUser('foo', 'bar', ['role1', 'role2']));

        $userData = new UserDataBag(null, null, '1.2.3.4', 'foo');
        $userData->setMetadata('type', 'InMemoryUser');
        $userData->setMetadata('roles', ['role1', 'role2']);

        $this->expectHubIsConfiguredWithValues($this->hub, 'user', $userData);

        $this->listener->onKernelRequest($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 1]],
            SentryUserListener::getSubscribedEvents(),
        );
    }
}
