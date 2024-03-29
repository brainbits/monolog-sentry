<?php

declare(strict_types=1);

namespace Brainbits\MonologSentry\EventListener;

use ReflectionClass;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SentryUserListener implements EventSubscriberInterface
{
    public function __construct(private HubInterface $hub, private Security $security)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $userData = new UserDataBag(null, null, $event->getRequest()->getClientIp());

        $user = $this->security->getUser();

        if ($user) {
            $userData->setUsername($user->getUserIdentifier());
            $userData->setMetadata('type', (new ReflectionClass($user))->getShortName());
            $userData->setMetadata('roles', $user->getRoles());
        }

        $this->hub->configureScope(
            static function (Scope $scope) use ($userData): void {
                $scope->setUser($userData);
            },
        );
    }

    /** @return mixed[] */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1],
        ];
    }
}
