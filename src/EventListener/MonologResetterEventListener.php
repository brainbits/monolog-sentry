<?php

declare(strict_types=1);

namespace Brainbits\MonologSentry\EventListener;

use InvalidArgumentException;
use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

use function assert;

// phpcs:disable Brainbits.Exception.GlobalException.GlobalException

final readonly class MonologResetterEventListener implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
        if (!$logger instanceof ResettableInterface) {
            throw new InvalidArgumentException('Logger needs to be resettable');
        }
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        $context = [
            'message' => $message,
            'error' => $event->getThrowable()->getMessage(),
            'class' => $message::class,
            'exception' => $event->getThrowable(),
        ];

        $this->logger->error('Error thrown while handling message {class}. Error: "{error}"', $context);

        $this->resetLogger();
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->resetLogger();
    }

    private function resetLogger(): void
    {
        assert($this->logger instanceof ResettableInterface);

        $this->logger->reset();
    }

    /** @return mixed[] */
    public static function getSubscribedEvents(): array
    {
        return [
            // It should be called after
            // \Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener
            // So that we have as much information as we can
            WorkerMessageFailedEvent::class => ['onMessageFailed', -200],
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ];
    }
}
