<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests\EventListener;

use Brainbits\MonologSentry\EventListener\MonologResetterEventListener;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/** @covers \Brainbits\MonologSentry\EventListener\MonologResetterEventListener */
final class MonologResetterEventListenerTest extends TestCase
{
    use HubExpections;

    public function testLoggerNeedsToBeResettable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Logger needs to be resettable');

        $logger = $this->createMock(LoggerInterface::class);

        new MonologResetterEventListener($logger);
    }

    public function testLoggerIsResetOnHandledMessage(): void
    {
        $event = new WorkerMessageHandledEvent(new Envelope(new stdClass()), 'foo');

        $logger = $this->createMock(ResettableLogger::class);
        $logger->expects($this->atLeastOnce())
            ->method('reset');

        $listener = new MonologResetterEventListener($logger);
        $listener->onMessageHandled($event);
    }

    public function testLoggerIsResetOnFailedMessage(): void
    {
        $event = new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'foo', new InvalidArgumentException());

        $logger = $this->createMock(ResettableLogger::class);
        $logger->expects($this->atLeastOnce())
            ->method('error');
        $logger->expects($this->atLeastOnce())
            ->method('reset');

        $listener = new MonologResetterEventListener($logger);
        $listener->onMessageFailed($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            [
                WorkerMessageFailedEvent::class => ['onMessageFailed', -200],
                WorkerMessageHandledEvent::class => 'onMessageHandled',
            ],
            MonologResetterEventListener::getSubscribedEvents(),
        );
    }
}
