<?php

declare(strict_types=1);

namespace Brainbits\MonologSentry\EventListener;

use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SentryRequestListener implements EventSubscriberInterface
{
    /** @phpstan-ignore-next-line */
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private bool $logInternalServerError = false,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$event->getRequest()->attributes->has('_route')) {
            return;
        }

        // @phpstan-ignore-next-line
        $matchedRoute = (string) $event->getRequest()->attributes->get('_route');

        $this->hub->configureScope(
            static function (Scope $scope) use ($matchedRoute): void {
                $scope->setTag('route', $matchedRoute);
            },
        );
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $statusCode = $event->getResponse()->getStatusCode();

        $this->hub->configureScope(
            static function (Scope $scope) use ($statusCode): void {
                $scope->setTag('status_code', (string) $statusCode);
            },
        );

        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if ($statusCode >= 500 && $this->logInternalServerError) {
            // 5XX response are private/security data safe so let's log them for debugging purpose
            $this->logger->error('500 returned', ['response' => $event->getResponse()]);
        }
    }

    /** @return mixed[] */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10000],
            KernelEvents::TERMINATE => ['onKernelTerminate', 1],
        ];
    }
}
